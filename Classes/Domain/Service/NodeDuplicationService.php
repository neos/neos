<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\Service;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeCreation\Dto\NodeAggregateIdsByNodePaths;
use Neos\ContentRepository\Core\Feature\NodeDuplication\Dto\NodeAggregateIdMapping;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindSubtreeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodePath;
use Neos\ContentRepository\Core\Projection\ContentGraph\Subtree;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Neos\Domain\Service\NodeDuplication\Commands;
use Neos\Neos\Domain\Service\NodeDuplication\TransientNode;

final class NodeDuplicationService
{
    public function __construct(
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry
    ) {
    }

    // todo, add additional property values for first node!
    public function copyNodesRecursively(
        ContentRepositoryId $contentRepositoryId,
        WorkspaceName $workspaceName,
        DimensionSpacePoint $sourceDimensionSpacePoint,
        NodeAggregateId $sourceNodeAggregateId,
        OriginDimensionSpacePoint $targetDimensionSpacePoint,
        NodeAggregateId $targetParentNodeAggregateId,
        ?NodeName $targetNodeName, // todo
        ?NodeAggregateId $targetSucceedingSiblingNodeAggregateId,  // todo
        NodeAggregateIdMapping $nodeAggregateIdMapping
    ): void {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $subgraph = $contentRepository->getContentGraph($workspaceName)->getSubgraph($sourceDimensionSpacePoint, VisibilityConstraints::withoutRestrictions());

        $subtree = $subgraph->findSubtree($sourceNodeAggregateId, FindSubtreeFilter::create());
        $targetParentNode = $subgraph->findNodeById($targetParentNodeAggregateId);
        if ($targetParentNode === null) {
            // todo simple constraint checks
            throw new \RuntimeException('todo');
        }

        $transientNode = TransientNode::forRegular(
            $nodeAggregateIdMapping->getNewNodeAggregateId($subtree->node->aggregateId) ?? NodeAggregateId::create(),
            $workspaceName,
            $targetDimensionSpacePoint,
            $subtree->node->nodeTypeName,
            NodeAggregateIdsByNodePaths::createEmpty(),
            $contentRepository->getNodeTypeManager()
        );

        $createCopyOfNodeCommand = CreateNodeAggregateWithNode::create(
            $workspaceName,
            $transientNode->aggregateId,
            $subtree->node->nodeTypeName,
            $targetDimensionSpacePoint,
            $targetParentNodeAggregateId,
            succeedingSiblingNodeAggregateId: $targetSucceedingSiblingNodeAggregateId,
            // todo skip properties not in schema
            initialPropertyValues: PropertyValuesToWrite::fromArray(
                iterator_to_array($subtree->node->properties)
            ),
        // todo references:
        );

        if ($targetNodeName) {
            $createCopyOfNodeCommand = $createCopyOfNodeCommand->withNodeName($targetNodeName);
        }

        $tetheredDescendantNodeAggregateIds = $this->getTetheredDescendantNodeAggregateIds(
            $subtree,
            $nodeAggregateIdMapping,
            NodePath::forRoot(),
            NodeAggregateIdsByNodePaths::createEmpty()
        );

        $createCopyOfNodeCommand = $createCopyOfNodeCommand->withTetheredDescendantNodeAggregateIds(
            $tetheredDescendantNodeAggregateIds
        );

        $transientNode = $transientNode->withTetheredNodeAggregateIds($tetheredDescendantNodeAggregateIds);

        $commands = Commands::create($createCopyOfNodeCommand);

        foreach ($subtree->children as $childSubtree) {
            $commands = $this->commandsForSubtreeRecursively($transientNode, $childSubtree, $nodeAggregateIdMapping, $commands);
        }

        foreach ($commands as $command) {
            $contentRepository->handle($command);
        }
    }

    private function commandsForSubtreeRecursively(TransientNode $transientParentNode, Subtree $subtree, NodeAggregateIdMapping $nodeAggregateIdMapping, Commands $commands): Commands
    {
        if ($subtree->node->classification->isTethered()) {
            $transientNode = $transientParentNode->forTetheredChildNode(
                $subtree->node->name
            );

            if ($subtree->node->properties->count() !== 0) {
                $setPropertiesOfTetheredNodeCommand = SetNodeProperties::create(
                    $transientParentNode->workspaceName,
                    $transientNode->aggregateId,
                    $transientParentNode->originDimensionSpacePoint,
                    PropertyValuesToWrite::fromArray(
                        iterator_to_array($subtree->node->properties)
                    ),
                );
                // todo references:

                $commands = $commands->append($setPropertiesOfTetheredNodeCommand);
            }

        } else {
            $transientNode = $transientParentNode->forRegularChildNode(
                $nodeAggregateIdMapping->getNewNodeAggregateId($subtree->node->aggregateId) ?? NodeAggregateId::create(),
                $subtree->node->nodeTypeName
            );

            $createCopyOfNodeCommand = CreateNodeAggregateWithNode::create(
                $transientParentNode->workspaceName,
                $transientNode->aggregateId,
                $subtree->node->nodeTypeName,
                $transientParentNode->originDimensionSpacePoint,
                $transientParentNode->aggregateId,
                // todo succeedingSiblingNodeAggregateId
                // todo skip properties not in schema
                initialPropertyValues: PropertyValuesToWrite::fromArray(
                    iterator_to_array($subtree->node->properties)
                ),
            // todo references:
            );

            $tetheredDescendantNodeAggregateIds = $this->getTetheredDescendantNodeAggregateIds(
                $subtree,
                $nodeAggregateIdMapping,
                NodePath::forRoot(),
                NodeAggregateIdsByNodePaths::createEmpty()
            );

            $createCopyOfNodeCommand = $createCopyOfNodeCommand->withTetheredDescendantNodeAggregateIds(
                $tetheredDescendantNodeAggregateIds
            );

            $transientNode = $transientNode->withTetheredNodeAggregateIds($tetheredDescendantNodeAggregateIds);

            $commands = $commands->append($createCopyOfNodeCommand);
        }

        foreach ($subtree->children as $childSubtree) {
            $commands = $commands->merge(
                $this->commandsForSubtreeRecursively($transientNode, $childSubtree, $nodeAggregateIdMapping, $commands)
            );
        }

        return $commands;
    }

    private function getTetheredDescendantNodeAggregateIds(Subtree $subtree, NodeAggregateIdMapping $nodeAggregateIdMapping, NodePath $nodePath, NodeAggregateIdsByNodePaths $tetheredNodeAggregateIds): NodeAggregateIdsByNodePaths
    {
        foreach ($subtree->children as $childSubtree) {
            if (!$childSubtree->node->classification->isTethered()) {
                continue;
            }

            $deterministicCopyAggregateId = $nodeAggregateIdMapping->getNewNodeAggregateId($childSubtree->node->aggregateId) ?? NodeAggregateId::create();

            $childNodePath = $nodePath->appendPathSegment($childSubtree->node->name);

            $tetheredNodeAggregateIds = $tetheredNodeAggregateIds->add(
                $childNodePath,
                $deterministicCopyAggregateId
            );

            $tetheredNodeAggregateIds = $this->getTetheredDescendantNodeAggregateIds($childSubtree, $nodeAggregateIdMapping, $childNodePath, $tetheredNodeAggregateIds);
        }

        return $tetheredNodeAggregateIds;
    }
}
