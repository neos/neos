<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\EventStore\EventInterface;
use Neos\ContentRepository\Core\Feature\Common\EmbedsNodeAggregateId;
use Neos\ContentRepository\Core\Feature\Common\EmbedsWorkspaceName;
use Neos\ContentRepository\Core\Feature\NodeCreation\Event\NodeAggregateWithNodeWasCreated;
use Neos\ContentRepository\Core\Feature\NodeModification\Event\NodePropertiesWereSet;
use Neos\ContentRepository\Core\Feature\NodeMove\Event\NodeAggregateWasMoved;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Event\NodeReferencesWereSet;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Event\NodeAggregateWasRemoved;
use Neos\ContentRepository\Core\Feature\NodeRenaming\Event\NodeAggregateNameWasChanged;
use Neos\ContentRepository\Core\Feature\NodeTypeChange\Event\NodeAggregateTypeWasChanged;
use Neos\ContentRepository\Core\Feature\NodeVariation\Event\NodeGeneralizationVariantWasCreated;
use Neos\ContentRepository\Core\Feature\NodeVariation\Event\NodePeerVariantWasCreated;
use Neos\ContentRepository\Core\Feature\NodeVariation\Event\NodeSpecializationVariantWasCreated;
use Neos\ContentRepository\Core\Feature\RootNodeCreation\Event\RootNodeAggregateDimensionsWereUpdated;
use Neos\ContentRepository\Core\Feature\RootNodeCreation\Event\RootNodeAggregateWithNodeWasCreated;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Event\SubtreeWasTagged;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Event\SubtreeWasUntagged;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Event\WorkspaceWasDiscarded;
use Neos\ContentRepository\Core\Feature\WorkspacePublication\Event\WorkspaceWasPublished;
use Neos\ContentRepository\Core\Feature\WorkspaceRebase\Event\WorkspaceWasRebased;
use Neos\ContentRepository\Core\Projection\CatchUpHook\CatchUpHookInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregate;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdsWithDimensionSpacePoints;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdWithDimensionSpacePoints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\Subscription\SubscriptionStatus;
use Neos\EventStore\Model\EventEnvelope;

final class ImpendingHardRemovalConflictDetectionHook implements CatchUpHookInterface
{
    public function __construct(
        private ContentRepositoryId $contentRepositoryId,
        private ContentGraphReadModelInterface $contentGraphReadModel,
        private ImpendingHardRemovalConflictRepository $impendingConflictRepository
    ) {
    }

    public function onBeforeCatchUp(SubscriptionStatus $subscriptionStatus): void
    {
    }

    public function onBeforeEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        if (!$eventInstance instanceof EmbedsNodeAggregateId || !$eventInstance instanceof EmbedsWorkspaceName || $eventInstance->getWorkspaceName()->isLive()) {
            return;
        }

        $contentGraph = $this->contentGraphReadModel->getContentGraph($eventInstance->getWorkspaceName());

        $nodeAggregate = $contentGraph->findNodeAggregateById($eventInstance->getNodeAggregateId());

        if ($nodeAggregate === null) {
            return;
        }

        $dimensionSpacePoints = match ($eventInstance::class) {
            NodeAggregateWasMoved::class => $eventInstance->succeedingSiblingsForCoverage->toDimensionSpacePointSet(),
            NodeAggregateWasRemoved::class => $eventInstance->affectedCoveredDimensionSpacePoints,
            default => null
        };

        if ($dimensionSpacePoints === null) {
            return;
        }

        $explicitlySoftRemovedAncestors = $this->findClosestExplicitlySoftRemovedNodes($contentGraph, $nodeAggregate, $dimensionSpacePoints);

        if ($explicitlySoftRemovedAncestors->isEmpty()) {
            return;
        }

        $this->impendingConflictRepository->addConflict(
            $this->contentRepositoryId,
            $eventInstance->getWorkspaceName(),
            $explicitlySoftRemovedAncestors
        );
    }

    public function onAfterEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        // todo WorkspaceWasPublished does not implement EmbedsWorkspaceName: https://github.com/neos/neos-development-collection/pull/5431
        $flushWorkspace = match ($eventInstance::class) {
            WorkspaceWasDiscarded::class => $eventInstance->workspaceName,
            WorkspaceWasPublished::class => $eventInstance->sourceWorkspaceName,
            WorkspaceWasRebased::class => $eventInstance->workspaceName,
            default => null
        };

        if ($flushWorkspace) {
            $this->impendingConflictRepository->pruneConflictsForWorkspace($this->contentRepositoryId, $flushWorkspace);
            return;
        }

        if (!$eventInstance instanceof EmbedsNodeAggregateId || !$eventInstance instanceof EmbedsWorkspaceName || $eventInstance->getWorkspaceName()->isLive()) {
            return;
        }

        $contentGraph = $this->contentGraphReadModel->getContentGraph($eventInstance->getWorkspaceName());

        $nodeAggregate = $contentGraph->findNodeAggregateById($eventInstance->getNodeAggregateId());

        if ($nodeAggregate === null) {
            return;
        }

        $dimensionSpacePoints = match ($eventInstance::class) {
            NodeAggregateWasMoved::class => $eventInstance->succeedingSiblingsForCoverage->toDimensionSpacePointSet(),
            NodePropertiesWereSet::class => $eventInstance->affectedDimensionSpacePoints,
            NodeAggregateWithNodeWasCreated::class => $eventInstance->succeedingSiblingsForCoverage->toDimensionSpacePointSet(),
            NodeReferencesWereSet::class => DimensionSpacePointSet::fromArray(array_merge(...array_map(
                fn (OriginDimensionSpacePoint $sourceOrigin): array => $nodeAggregate->getCoverageByOccupant($sourceOrigin)->points,
                array_values($eventInstance->affectedSourceOriginDimensionSpacePoints->getPoints())
            ))),
            SubtreeWasTagged::class,
            SubtreeWasUntagged::class => $eventInstance->affectedDimensionSpacePoints,
            NodePeerVariantWasCreated::class => DimensionSpacePointSet::fromArray([$eventInstance->peerOrigin->toDimensionSpacePoint(), $eventInstance->sourceOrigin->toDimensionSpacePoint()]),
            NodeGeneralizationVariantWasCreated::class => DimensionSpacePointSet::fromArray([$eventInstance->generalizationOrigin->toDimensionSpacePoint(), $eventInstance->sourceOrigin->toDimensionSpacePoint()]),
            NodeSpecializationVariantWasCreated::class => DimensionSpacePointSet::fromArray([$eventInstance->specializationOrigin->toDimensionSpacePoint(), $eventInstance->sourceOrigin->toDimensionSpacePoint()]),
            NodeAggregateNameWasChanged::class,
            NodeAggregateTypeWasChanged::class => $nodeAggregate->coveredDimensionSpacePoints,
            RootNodeAggregateDimensionsWereUpdated::class => $eventInstance->coveredDimensionSpacePoints,
            default => null, /** explicitly also @see RootNodeAggregateWithNodeWasCreated, which cannot conflict with hard removal */
        };

        if ($dimensionSpacePoints === null) {
            return;
        }

        $explicitlySoftRemovedAncestors = $this->findClosestExplicitlySoftRemovedNodes($contentGraph, $nodeAggregate, $dimensionSpacePoints);

        if (!$explicitlySoftRemovedAncestors->isEmpty()) {
            $this->impendingConflictRepository->addConflict(
                $this->contentRepositoryId,
                $eventInstance->getWorkspaceName(),
                $explicitlySoftRemovedAncestors
            );
        }

        if ($eventInstance instanceof NodeReferencesWereSet) {
            foreach ($eventInstance->references as $serializedReference) {
                foreach ($serializedReference->references as $reference) {
                    $referenceAggregate = $contentGraph->findNodeAggregateById($reference->targetNodeAggregateId);
                    if ($referenceAggregate instanceof NodeAggregate) {
                        $explicitlySoftRemovedReferenceAncestors = $this->findClosestExplicitlySoftRemovedNodes($contentGraph, $referenceAggregate, $dimensionSpacePoints);
                        if (!$explicitlySoftRemovedReferenceAncestors->isEmpty()) {
                            $this->impendingConflictRepository->addConflict(
                                $this->contentRepositoryId,
                                $eventInstance->getWorkspaceName(),
                                $explicitlySoftRemovedReferenceAncestors
                            );
                        }
                    }
                }
            }
        }
    }

    private function findClosestExplicitlySoftRemovedNodes(
        ContentGraphInterface $contentGraph,
        NodeAggregate $entryNodeAggregate,
        DimensionSpacePointSet $dimensionSpacePoints
    ): NodeAggregateIdsWithDimensionSpacePoints {
        /** @var array<NodeAggregate> $stack */
        $stack = [$entryNodeAggregate];

        $explicitlySoftRemovedAncestors = NodeAggregateIdsWithDimensionSpacePoints::create();
        while ($stack !== []) {
            $nodeAggregate = array_shift($stack);
            $explicitlySoftRemovedDimensions = $nodeAggregate->getDimensionSpacePointsTaggedWith(SubtreeTag::removed())->getIntersection($dimensionSpacePoints);
            if (!$explicitlySoftRemovedDimensions->isEmpty()) {
                $explicitlySoftRemovedAncestors = $explicitlySoftRemovedAncestors->with(NodeAggregateIdWithDimensionSpacePoints::create(
                    $nodeAggregate->nodeAggregateId,
                    $explicitlySoftRemovedDimensions
                ));
            }
            // todo we must stop if the current node aggregate is not by inheritance tagged via removed as otherwise we end up always traversing the whole tree up
            $stack = [...$stack, ...iterator_to_array($contentGraph->findParentNodeAggregates($nodeAggregate->nodeAggregateId))];
        }

        return $explicitlySoftRemovedAncestors;
    }

    public function onAfterBatchCompleted(): void
    {
    }

    public function onAfterCatchUp(): void
    {
    }
}
