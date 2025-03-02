<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Command\RemoveNodeAggregate;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregateCurrentlyDoesNotExist;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint;
use Neos\ContentRepository\Core\SharedModel\Node\NodeVariantSelectionStrategy;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Neos\Domain\Service\WorkspacePublishingService;

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

    /**
     * Triggering of the soft removal garbage collection is done via the {@see WorkspacePublishingService}
     *
     * *Invalidation*
     *
     * For these commands the contents of the workspace change and afterward there are less pending changes - e.g. less impending conflicts.
     * Which means that we can trigger the garbage collection in hope to have something cleaned up.
     *
     * - PublishWorkspace
     * - PublishIndividualNodesFromWorkspace
     * - DiscardIndividualNodesFromWorkspace
     * - DiscardWorkspace
     * - DeleteWorkspace
     * - RebaseWorkspace
     *
     * The command `RebaseWorkspace` is a special case. Because (expect for force-rebase with dropped changes) no changes are expected to be dropped
     * and thus there are no subtractions from the impending conflicts. Instead, the rebase takes care of synchronizing the new soft removals into the workspace.
     * New soft removals can either cause new impending conflicts or free the claim of the workspace as the node will no longer be visible.
     *
     * *Sync vs async*
     *
     * In the future we could allow to disable the garbage collection being run synchronously but offer an async job.
     *
     * *Impediments*
     *
     * In a single user system the garbage collection will immediately turn the users workspace outdated after publishing a non-conflicting removal.
     */
    public function run(ContentRepositoryId $contentRepositoryId): void
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $softRemovedNodes = $this->findNodeAggregatesInWorkspaceByExplicitRemovedTag($contentRepository->getContentGraph(WorkspaceName::forLive()));

        $softRemovedNodes = $this->withVisibleInDependingWorkspacesConflicts($softRemovedNodes, $contentRepository);

        $softRemovedNodes = $this->withImpendingHardRemovalConflicts($softRemovedNodes, $contentRepository);

        foreach ($softRemovedNodes as $softRemovedNode) {
            // the generalisations of the non-conflicting soft removed dimensions
            $generalizationsToRemoveWithAllSpecializations = $contentRepository->getVariationGraph()->reduceSetToRelativeRoots(
                $softRemovedNode->removedDimensionSpacePoints
                    ->getDifference($softRemovedNode->conflictingDimensionSpacePoints)
            );
            foreach ($generalizationsToRemoveWithAllSpecializations as $generalization) {
                if (
                    // check if any of the affected dimensions (STRATEGY_ALL_SPECIALIZATIONS) for the $generalization
                    // impose a conflict
                    $contentRepository->getVariationGraph()->getSpecializationSet($generalization)
                        ->getIntersection($softRemovedNode->conflictingDimensionSpacePoints)
                        ->isEmpty()
                ) {
                    try {
                        $contentRepository->handle(RemoveNodeAggregate::create(
                            WorkspaceName::forLive(),
                            $softRemovedNode->nodeAggregateId,
                            $generalization,
                            NodeVariantSelectionStrategy::STRATEGY_ALL_SPECIALIZATIONS
                        ));
                    } catch (NodeAggregateCurrentlyDoesNotExist | NodeAggregateDoesCurrentlyNotCoverDimensionSpacePoint) {
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
    private function withVisibleInDependingWorkspacesConflicts(SoftRemovedNodes $softRemovedNodes, ContentRepository $contentRepository): SoftRemovedNodes
    {
        $allWorkspace = $contentRepository->findWorkspaces();

        $namesOfWorkspacesDependingOnLive = [];
        $stack = iterator_to_array($allWorkspace->getDependantWorkspaces(WorkspaceName::forLive()));
        while ($stack !== []) {
            $workspace = array_shift($stack);
            $namesOfWorkspacesDependingOnLive[] = $workspace->workspaceName;
            $stack = [...$stack, ...iterator_to_array($allWorkspace->getDependantWorkspaces($workspace->workspaceName))];
        }

        foreach ($namesOfWorkspacesDependingOnLive as $workspaceName) {
            $contentGraph = $contentRepository->getContentGraph($workspaceName);

            $nodeAggregatesInWorkspace = $contentGraph->findNodeAggregatesByIds($softRemovedNodes->toNodeAggregateIds());

            foreach ($softRemovedNodes as $softRemovedNode) {
                $nodeAggregateInWorkspace = $nodeAggregatesInWorkspace->get($softRemovedNode->nodeAggregateId);
                if ($nodeAggregateInWorkspace === null) {
                    continue;
                }
                $softDeletedDimensionsInWorkspace = $nodeAggregateInWorkspace->getCoveredDimensionsTaggedBy(SubtreeTag::removed(), withoutInherited: true);
                $notSoftDeletedDimensionsInWorkspace = $nodeAggregateInWorkspace->coveredDimensionSpacePoints->getDifference($softDeletedDimensionsInWorkspace);

                $softRemovedNodes = $softRemovedNodes->with(
                    $softRemovedNode->withConflictingDimensionSpacePoints(
                        $notSoftDeletedDimensionsInWorkspace
                    )
                );
            }
        }

        return $softRemovedNodes;
    }

    /**
     * Workspace that are already synchronised with live know the soft removals. This means that all pending changes have been rebased
     * on live and at the time of the rebase each of the events was aware of soft removal. The conflicts are remembered via hook.
     */
    private function withImpendingHardRemovalConflicts(SoftRemovedNodes $softRemovedNodes, ContentRepository $contentRepository): SoftRemovedNodes
    {
        $impendingConflicts = $this->impendingConflictRepository->findAllConflicts($contentRepository->id);

        foreach ($softRemovedNodes as $softRemovedNode) {
            $impendingConflict = $impendingConflicts->get($softRemovedNode->nodeAggregateId);
            if ($impendingConflict === null) {
                continue;
            }
            $softRemovedNodes = $softRemovedNodes->with(
                $softRemovedNode->withConflictingDimensionSpacePoints(
                    $softRemovedNode->conflictingDimensionSpacePoints->getUnion($impendingConflict->dimensionSpacePointSet)
                )
            );
        }

        return $softRemovedNodes;
    }

    private function findNodeAggregatesInWorkspaceByExplicitRemovedTag(ContentGraphInterface $contentGraph): SoftRemovedNodes
    {
        $softRemovedNodes = [];
        foreach ($contentGraph->findNodeAggregatesTaggedBy(SubtreeTag::removed()) as $nodeAggregateTaggedRemoved) {
            if ($nodeAggregateTaggedRemoved->classification->isRoot()) {
                // we don't handle the soft removal of root nodes because root nodes cannot be removed via `STRATEGY_ALL_SPECIALIZATIONS`
                continue;
            }
            $softRemovedNodes[] = SoftRemovedNode::create(
                $nodeAggregateTaggedRemoved->nodeAggregateId,
                $nodeAggregateTaggedRemoved->getCoveredDimensionsTaggedBy(SubtreeTag::removed(), withoutInherited: true)
            );
        }
        return SoftRemovedNodes::fromArray($softRemovedNodes);
    }
}
