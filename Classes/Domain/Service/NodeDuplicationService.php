<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\Service;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Command\CreateNodeAggregateWithNode;
use Neos\ContentRepository\Core\Feature\NodeModification\Command\SetNodeProperties;
use Neos\ContentRepository\Core\Feature\NodeModification\Dto\PropertyValuesToWrite;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Command\SetNodeReferences;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferencesForName;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferencesToWrite;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Dto\NodeReferenceToWrite;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindReferencesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindSubtreeFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\References;
use Neos\ContentRepository\Core\Projection\ContentGraph\Subtree;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregateCurrentlyDoesNotExist;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Node\ReferenceName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Neos\Domain\Exception\TetheredNodesCannotBePartiallyCopied;
use Neos\Neos\Domain\Service\NodeDuplication\Commands;
use Neos\Neos\Domain\Service\NodeDuplication\NodeAggregateIdMapping;
use Neos\Neos\Domain\Service\NodeDuplication\TransientNodeCopy;

final class NodeDuplicationService
{
    public function __construct(
        private readonly ContentRepositoryRegistry $contentRepositoryRegistry
    ) {
    }

    public function copyNodesRecursively(
        ContentRepositoryId $contentRepositoryId,
        WorkspaceName $workspaceName,
        DimensionSpacePoint $sourceDimensionSpacePoint,
        NodeAggregateId $sourceNodeAggregateId,
        OriginDimensionSpacePoint $targetDimensionSpacePoint,
        NodeAggregateId $targetParentNodeAggregateId,
        ?NodeAggregateId $targetSucceedingSiblingNodeAggregateId,
        NodeAggregateIdMapping $nodeAggregateIdMapping
    ): void {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $subgraph = $contentRepository->getContentGraph($workspaceName)->getSubgraph($sourceDimensionSpacePoint, VisibilityConstraints::withoutRestrictions());

        $targetParentNode = $subgraph->findNodeById($targetParentNodeAggregateId);
        if ($targetParentNode === null) {
            throw new NodeAggregateCurrentlyDoesNotExist(sprintf('The target parent node aggregate "%s" does not exist', $targetParentNodeAggregateId->value));
        }

        $subtree = $subgraph->findSubtree($sourceNodeAggregateId, FindSubtreeFilter::create());
        if ($subtree === null) {
            throw new NodeAggregateCurrentlyDoesNotExist(sprintf('The source node aggregate "%s" does not exist', $sourceNodeAggregateId->value));
        }

        $transientNodeCopy = TransientNodeCopy::forEntry(
            $subtree,
            $workspaceName,
            $targetDimensionSpacePoint,
            $nodeAggregateIdMapping
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
            $commands = $this->commandsForSubtreeRecursively($transientNodeCopy, $childSubtree, $subgraph, $commands);
        }

        foreach ($commands as $command) {
            $contentRepository->handle($command);
        }
    }

    private function commandsForSubtreeRecursively(TransientNodeCopy $transientParentNode, Subtree $subtree, ContentSubgraphInterface $subgraph, Commands $commands): Commands
    {
        if ($subtree->node->classification->isTethered()) {
            $transientNode = $transientParentNode->forTetheredChildNode(
                $subtree
            );

            if ($subtree->node->properties->count() > 0) {
                $setPropertiesOfTetheredNodeCommand = SetNodeProperties::create(
                    $transientParentNode->workspaceName,
                    $transientNode->aggregateId,
                    $transientParentNode->originDimensionSpacePoint,
                    // todo skip properties not in schema
                    PropertyValuesToWrite::fromArray(
                        iterator_to_array($subtree->node->properties)
                    ),
                );

                $commands = $commands->append($setPropertiesOfTetheredNodeCommand);
            }

            $references = $subgraph->findReferences($subtree->node->aggregateId, FindReferencesFilter::create());
            if ($references->count() > 0) {
                $setReferencesOfTetheredNodeCommand = SetNodeReferences::create(
                    $transientParentNode->workspaceName,
                    $transientNode->aggregateId,
                    $transientParentNode->originDimensionSpacePoint,
                    $this->serializeProjectedReferences(
                        $references
                    ),
                );

                $commands = $commands->append($setReferencesOfTetheredNodeCommand);
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
                references: $this->serializeProjectedReferences(
                    $subgraph->findReferences($subtree->node->aggregateId, FindReferencesFilter::create())
                )
            );

            $createCopyOfNodeCommand = $createCopyOfNodeCommand->withTetheredDescendantNodeAggregateIds(
                $transientNode->tetheredNodeAggregateIds
            );

            $commands = $commands->append($createCopyOfNodeCommand);
        }

        foreach ($subtree->children as $childSubtree) {
            $commands = $this->commandsForSubtreeRecursively($transientNode, $childSubtree, $subgraph, $commands);
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
