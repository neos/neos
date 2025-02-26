<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\DimensionSpace\VariantType;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Command\RemoveNodeAggregate;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindRootNodeAggregatesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregate;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdsWithDimensionSpacePoints;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdWithDimensionSpacePoints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregateCurrentlyDoesNotExist;
use Neos\ContentRepository\Core\SharedModel\Node\NodeVariantSelectionStrategy;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;

/**
 * Service that detects which soft removals in a content repository can be safely transformed to hard removals
 * and executes those.
 *
 * The detection/execution is done in the following steps:
 * 1) find all soft removals in the root workspace(s)
 * For each soft removal
 * 2) check if any workspace objects to the transformation (i.e. would run into conflicts on rebase)
 * 3) if there are no impending conflicts, execute the hard removal on the root workspace(s)
 * 4) the hard removal will then propagate to the other workspace automatically via rebase
 *
 * @internal safe to run at any time, but manual runs should be unnecessary
 */
final readonly class SoftRemovalGarbageCollector
{
    public function __construct(
        private ContentRepositoryRegistry $contentRepositoryRegistry,
        private ImpendingHardRemovalConflictRepository $impendingConflictRepository,
    ) {
    }

    public function run(ContentRepositoryId $contentRepositoryId): void
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $liveSoftRemovals = $this->findNodeAggregatesInWorkspaceByExplicitRemovedTag($contentRepository->getContentGraph(WorkspaceName::forLive()));

        $softRemovalsAcrossWorkspaces = $this->subtractNodesWhichAreNotSoftRemovedInOtherWorkspaces($liveSoftRemovals, $contentRepository);

        $softRemovalsWithoutImpendingConflicts = $this->subtractImpendingConflicts($softRemovalsAcrossWorkspaces, $contentRepository);
        foreach ($softRemovalsWithoutImpendingConflicts as $softRemovedNode) {
            if ($softRemovedNode->dimensionSpacePointSet->isEmpty()) {
                // only impending conflicts
                continue;
            }
            $generalizationsToRemoveWithAllSpecializations = $softRemovedNode->dimensionSpacePointSet->points;
            foreach ($softRemovedNode->dimensionSpacePointSet as $dimensionSpacePointA) {
                foreach ($softRemovedNode->dimensionSpacePointSet as $dimensionSpacePointB) {
                    switch ($contentRepository->getVariationGraph()->getVariantType($dimensionSpacePointA, $dimensionSpacePointB)) {
                        case VariantType::TYPE_SPECIALIZATION:
                            unset($generalizationsToRemoveWithAllSpecializations[$dimensionSpacePointA->hash]);
                            break;
                        default:
                    }
                }
            }
            foreach ($generalizationsToRemoveWithAllSpecializations as $generalization) {
                if (
                    // @todo invert logic: compare specialization set with impending conflicts
                    $contentRepository->getVariationGraph()->getSpecializationSet($generalization)
                        ->getDifference($softRemovedNode->dimensionSpacePointSet)
                        ->isEmpty()
                ) {
                    try {
                        $contentRepository->handle(RemoveNodeAggregate::create(
                            WorkspaceName::forLive(),
                            $softRemovedNode->nodeAggregateId,
                            $generalization,
                            NodeVariantSelectionStrategy::STRATEGY_ALL_SPECIALIZATIONS
                        ));
                    } catch (NodeAggregateCurrentlyDoesNotExist) {
                        // already removed by another command further up the graph
                    }
                }
            }
        }
    }

    /**
     * If a workspace is outdated and does not have the soft removal yet, but knows the node we cannot safely remove the node as
     * the user can still make changes to that node or children any time in the future - or might have done so already.
     * Heavily outdated workspaces might not even know the node yet and thus impose NO conflict.
     */
    private function subtractNodesWhichAreNotSoftRemovedInOtherWorkspaces(NodeAggregateIdsWithDimensionSpacePoints $liveSoftRemovals, ContentRepository $contentRepository): NodeAggregateIdsWithDimensionSpacePoints
    {
        $softRemovedNodesAcrossWorkspaces = $liveSoftRemovals;

        foreach ($contentRepository->findWorkspaces() as $workspace) {
            if ($workspace->isRootWorkspace()) {
                // todo ignore non base workspaces of live?!
                continue;
            }
            $contentGraph = $contentRepository->getContentGraph($workspace->workspaceName);

            $nodeAggregatesInWorkspace = $contentGraph->findNodeAggregatesByIds($softRemovedNodesAcrossWorkspaces->toNodeAggregateIds());

            foreach ($softRemovedNodesAcrossWorkspaces as $softRemovedNode) {
                $nodeAggregateInWorkspace = $nodeAggregatesInWorkspace->get($softRemovedNode->nodeAggregateId);
                if ($nodeAggregateInWorkspace === null) {
                    continue;
                }
                $softDeletedDimensionsInWorkspace = $nodeAggregateInWorkspace->getDimensionSpacePointsTaggedWith(SubtreeTag::removed());
                $notSoftDeletedDimensionsInWorkspace = $nodeAggregateInWorkspace->coveredDimensionSpacePoints->getDifference($softDeletedDimensionsInWorkspace);

                $softRemovedNodesAcrossWorkspaces = $softRemovedNodesAcrossWorkspaces->with(NodeAggregateIdWithDimensionSpacePoints::create(
                    $softRemovedNode->nodeAggregateId,
                    $softRemovedNode->dimensionSpacePointSet->getDifference($notSoftDeletedDimensionsInWorkspace)
                ));
            }
        }

        return $softRemovedNodesAcrossWorkspaces;
    }

    /**
     * Workspace that are already synchronised with live know the soft removals. This means that all pending changes have been rebased
     * on live and at the time of the rebase each of the events was aware of soft removal. The conflicts are remembered via hook and will be checked here.
     */
    private function subtractImpendingConflicts(NodeAggregateIdsWithDimensionSpacePoints $softRemovals, ContentRepository $contentRepository): NodeAggregateIdsWithDimensionSpacePoints
    {
        $softRemovalsWithoutImpendingConflicts = $softRemovals;

        $impendingConflicts = $this->impendingConflictRepository->findAllConflicts($contentRepository->id);

        foreach ($softRemovalsWithoutImpendingConflicts as $softRemovedNode) {
            $impendingConflict = $impendingConflicts->get($softRemovedNode->nodeAggregateId);
            if ($impendingConflict === null) {
                continue;
            }
            $impendingConflictGeneralizationSet = $impendingConflict->dimensionSpacePointSet;
            foreach ($impendingConflict->dimensionSpacePointSet as $dimensionSpacePoint) {
                $impendingConflictGeneralizationSet = $impendingConflictGeneralizationSet->getUnion(
                    $contentRepository->getVariationGraph()->getIndexedGeneralizations($dimensionSpacePoint)
                );
            }
            $softRemovalsWithoutImpendingConflicts = $softRemovalsWithoutImpendingConflicts->with(NodeAggregateIdWithDimensionSpacePoints::create(
                $softRemovedNode->nodeAggregateId,
                $softRemovedNode->dimensionSpacePointSet->getDifference($impendingConflictGeneralizationSet)
            ));
        }

        return $softRemovalsWithoutImpendingConflicts;
    }

    private function findNodeAggregatesInWorkspaceByExplicitRemovedTag(ContentGraphInterface $contentGraph): NodeAggregateIdsWithDimensionSpacePoints
    {
        $softRemovedNodes = [];
        foreach ($contentGraph->findNodeAggregatesTaggedWith(SubtreeTag::removed()) as $nodeAggregateTaggedRemoved) {
            $softRemovedNodes[] = NodeAggregateIdWithDimensionSpacePoints::create(
                $nodeAggregateTaggedRemoved->nodeAggregateId,
                $nodeAggregateTaggedRemoved->getDimensionSpacePointsTaggedWith(SubtreeTag::removed())
            );
        }
        return NodeAggregateIdsWithDimensionSpacePoints::fromArray($softRemovedNodes);
    }
}
