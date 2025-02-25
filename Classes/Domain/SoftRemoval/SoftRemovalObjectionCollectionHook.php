<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
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

final class SoftRemovalObjectionCollectionHook implements CatchUpHookInterface
{
    public function __construct(
        private ContentRepositoryId $contentRepositoryId,
        private ContentGraphReadModelInterface $contentGraphReadModel,
        private SoftRemovalObjectionRepository $objectionRepository
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
            default => null
        };

        if ($dimensionSpacePoints === null) {
            return;
        }

        $explicitlySoftRemovedAncestors = $this->findClosestExplicitlySoftRemovedNodes($contentGraph, $nodeAggregate, $dimensionSpacePoints);

        if ($explicitlySoftRemovedAncestors->isEmpty()) {
            return;
        }

        $this->objectionRepository->addObjection(
            $this->contentRepositoryId,
            $eventInstance->getWorkspaceName(),
            $explicitlySoftRemovedAncestors
        );
    }

    public function onAfterEvent(EventInterface $eventInstance, EventEnvelope $eventEnvelope): void
    {
        $flushWorkspace = match($eventInstance::class) {
            WorkspaceWasDiscarded::class => $eventInstance->workspaceName,
            WorkspaceWasPublished::class => $eventInstance->sourceWorkspaceName,
            WorkspaceWasRebased::class => $eventInstance->workspaceName,
            default => null
        };

        if ($flushWorkspace) {
            $this->objectionRepository->flushWorkspace($this->contentRepositoryId, $flushWorkspace); // todo getWorkspaceName does not always work?!
            return;
        }

        if (!$eventInstance instanceof EmbedsNodeAggregateId || !$eventInstance instanceof EmbedsWorkspaceName || $eventInstance->getWorkspaceName()->isLive()) {
            return;
        }

        // todo
        // - UpdateRootNodeAggregateDimensions
        // - CreateRootNodeAggregateWithNode
        // or RootNodeAggregateDimensionsWereUpdated::class,
        // or RootNodeAggregateWithNodeWasCreated::class,


        // $anchorNodeAggregateId = match($eventInstance::class) {
        //     NodeAggregateWithNodeWasCreated::class,
        //     NodePropertiesWereSet::class,
        //     NodeAggregateWasMoved::class,
        //     NodeReferencesWereSet::class,
        //     NodeAggregateWasRemoved::class,
        //     NodeAggregateNameWasChanged::class,
        //     NodeAggregateTypeWasChanged::class,
        //     NodeGeneralizationVariantWasCreated::class,
        //     NodePeerVariantWasCreated::class,
        //     NodeSpecializationVariantWasCreated::class,
        //     SubtreeWasTagged::class,
        //     SubtreeWasUntagged::class => $eventInstance->nodeAggregateId
        // };


        $contentGraph = $this->contentGraphReadModel->getContentGraph($eventInstance->getWorkspaceName());

        $nodeAggregate = $contentGraph->findNodeAggregateById($eventInstance->getNodeAggregateId());

        if ($nodeAggregate === null) {
            return;
        }

        $dimensionSpacePoints = match ($eventInstance::class) {
            NodeAggregateWasMoved::class => $eventInstance->succeedingSiblingsForCoverage->toDimensionSpacePointSet(),
            NodePropertiesWereSet::class => $eventInstance->affectedDimensionSpacePoints,
            NodeAggregateWithNodeWasCreated::class => $eventInstance->succeedingSiblingsForCoverage->toDimensionSpacePointSet(),
            NodeReferencesWereSet::class => $eventInstance->affectedSourceOriginDimensionSpacePoints->toDimensionSpacePointSet(),
            SubtreeWasTagged::class,
            SubtreeWasUntagged::class => $eventInstance->affectedDimensionSpacePoints,
            NodeAggregateWasRemoved::class => $eventInstance->affectedCoveredDimensionSpacePoints,
            NodePeerVariantWasCreated::class => $eventInstance->peerOrigin->toDimensionSpacePoint(),
            NodeGeneralizationVariantWasCreated::class => $eventInstance->generalizationOrigin->toDimensionSpacePoint(),
            NodeAggregateNameWasChanged::class,
            NodeAggregateTypeWasChanged::class => $nodeAggregate->coveredDimensionSpacePoints,
            default => null
        };

        if ($dimensionSpacePoints === null) {
            return;
        }

        $explicitlySoftRemovedAncestors = $this->findClosestExplicitlySoftRemovedNodes($contentGraph, $nodeAggregate, $dimensionSpacePoints);

        if ($explicitlySoftRemovedAncestors->isEmpty()) {
            return;
        }

        $this->objectionRepository->addObjection(
            $this->contentRepositoryId,
            $eventInstance->getWorkspaceName(),
            $explicitlySoftRemovedAncestors
        );
    }

    private function findClosestExplicitlySoftRemovedNodes(ContentGraphInterface $contentGraph, NodeAggregate $entryNodeAggregate, DimensionSpacePointSet $dimensionSpacePoints): NodeAggregateIdsWithDimensionSpacePoints
    {
        /** @var array<NodeAggregate> $stack */
        $stack = [$entryNodeAggregate];

        $explicitlySoftRemovedAncestors = NodeAggregateIdsWithDimensionSpacePoints::create();
        while ($stack !== []) {
            $nodeAggregate = array_shift($stack);
            if ($this->nodeAggregateIsSoftRemovedInDimension($nodeAggregate, $dimensionSpacePoints)) {
                $explicitlySoftRemoved = $nodeAggregate->getDimensionSpacePointsTaggedWith(SubtreeTag::removed());
                if (!$explicitlySoftRemoved->isEmpty()) { // todo difference with $dimensionSpacePoints???
                    $explicitlySoftRemovedAncestors = $explicitlySoftRemovedAncestors->with(NodeAggregateIdWithDimensionSpacePoints::create(
                        $nodeAggregate->nodeAggregateId,
                        $dimensionSpacePoints
                    ));
                }
                $stack = [...$stack, ...iterator_to_array($contentGraph->findParentNodeAggregates($nodeAggregate->nodeAggregateId))];
            }
        }

        return $explicitlySoftRemovedAncestors;
    }

    private function nodeAggregateIsSoftRemovedInDimension(NodeAggregate $nodeAggregate, DimensionSpacePointSet $dimensionSpacePoints): bool
    {
        foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
            if ($nodeAggregate->coversDimensionSpacePoint($dimensionSpacePoint)) {
                $node = $nodeAggregate->getNodeByCoveredDimensionSpacePoint($dimensionSpacePoint);
                $nodeOrAncestorIsSoftRemoved = $node->tags->contain(SubtreeTag::removed());
                if ($nodeOrAncestorIsSoftRemoved) {
                    return true;
                }
            }
        }
        return false;
    }

    public function onAfterBatchCompleted(): void
    {
    }

    public function onAfterCatchUp(): void
    {
    }
}
