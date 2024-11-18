<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\Service;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeDuplication\Dto\NodeAggregateIdMapping;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferencesForName;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferencesToWrite;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferenceToWrite;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindReferencesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindSubtreeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\References;
use Neos\ContentRepository\Core\Projection\ContentGraph\Subtree;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Node\ReferenceName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Neos\Domain\Exception\TetheredNodesCannotBePartiallyCopied;
use Neos\Neos\Domain\Service\NodeDuplication\Commands;
use Neos\Neos\Domain\Service\NodeDuplication\TransientNodeCopy;

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
        ?NodeName $targetNodeName,
        ?NodeAggregateId $targetSucceedingSiblingNodeAggregateId,
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

        $transientNodeCopy = TransientNodeCopy::forRegular(
            $subtree,
            $workspaceName,
            $targetDimensionSpacePoint,
            $nodeAggregateIdMapping,
            $contentRepository->getNodeTypeManager()
        );

        $createCopyOfNodeCommand = CreateNodeAggregateWithNode::create(
            $workspaceName,
            $transientNodeCopy->aggregateId,
            $subtree->node->nodeTypeName,
            $targetDimensionSpacePoint,
            $targetParentNodeAggregateId,
            succeedingSiblingNodeAggregateId: $targetSucceedingSiblingNodeAggregateId,
            // todo skip properties not in schema
            initialPropertyValues: PropertyValuesToWrite::fromArray(
                iterator_to_array($subtree->node->properties)
            ),
            references: $this->serializeProjectedReferences(
                $subgraph->findReferences($subtree->node->aggregateId, FindReferencesFilter::create())
            )
        );

        if ($targetNodeName) {
            $createCopyOfNodeCommand = $createCopyOfNodeCommand->withNodeName($targetNodeName);
        }

        $createCopyOfNodeCommand = $createCopyOfNodeCommand->withTetheredDescendantNodeAggregateIds(
            $transientNodeCopy->tetheredNodeAggregateIds
        );

        $commands = Commands::create($createCopyOfNodeCommand);

        foreach ($subtree->children as $childSubtree) {
            if ($subtree->node->classification->isTethered() && $childSubtree->node->classification->isTethered()) {
                // TODO we assume here that the child node is tethered because the grandparent specifies that.
                // this is not always fully correct and we could loosen the constraint by checking the node type schema
                throw new TetheredNodesCannotBePartiallyCopied(sprintf('Cannot copy tethered node %s because child node %s is also tethered. Only standalone tethered nodes can be copied.', $subtree->node->aggregateId->value, $childSubtree->node->aggregateId->value), 1731264887);
            }
            $commands = $this->commandsForSubtreeRecursively($transientNodeCopy, $childSubtree, $commands);
        }

        foreach ($commands as $command) {
            $contentRepository->handle($command);
        }
    }

    private function commandsForSubtreeRecursively(TransientNodeCopy $transientParentNode, Subtree $subtree, Commands $commands): Commands
    {
        if ($subtree->node->classification->isTethered()) {
            $transientNode = $transientParentNode->forTetheredChildNode(
                $subtree
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
                $subtree
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

            $createCopyOfNodeCommand = $createCopyOfNodeCommand->withTetheredDescendantNodeAggregateIds(
                $transientNode->tetheredNodeAggregateIds
            );

            $commands = $commands->append($createCopyOfNodeCommand);
        }

        foreach ($subtree->children as $childSubtree) {
            $commands = $this->commandsForSubtreeRecursively($transientNode, $childSubtree, $commands);
        }

        return $commands;
    }

    private function serializeProjectedReferences(References $references): NodeReferencesToWrite
    {
        $serializedReferencesByName = [];
        foreach ($references as $reference) {
            if (!isset($serializedReferencesByName[$reference->name->value])) {
                $serializedReferencesByName[$reference->name->value] = [];
            }
            $serializedReferencesByName[$reference->name->value][] = NodeReferenceToWrite::fromTargetAndProperties($reference->node->aggregateId, $reference->properties?->count() > 0 ? PropertyValuesToWrite::fromArray(iterator_to_array($reference->properties)) : PropertyValuesToWrite::createEmpty());
        }

        $serializedReferences = [];
        foreach ($serializedReferencesByName as $name => $referenceObjects) {
            $serializedReferences[] = NodeReferencesForName::fromReferences(ReferenceName::fromString($name), $referenceObjects);
        }

        return NodeReferencesToWrite::fromArray($serializedReferences);
    }
}
