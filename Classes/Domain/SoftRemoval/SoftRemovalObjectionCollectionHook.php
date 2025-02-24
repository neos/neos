<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Neos\ContentRepository\Core\EventStore\EventInterface;
use Neos\ContentRepository\Core\Feature\Common\EmbedsNodeAggregateId;
use Neos\ContentRepository\Core\Feature\Common\EmbedsWorkspaceName;
use Neos\ContentRepository\Core\Feature\NodeCreation\Event\NodeAggregateWithNodeWasCreated;
use Neos\ContentRepository\Core\Feature\NodeModification\Event\NodePropertiesWereSet;
use Neos\ContentRepository\Core\Feature\NodeMove\Event\NodeAggregateWasMoved;
use Neos\ContentRepository\Core\Feature\NodeReferencing\Event\NodeReferencesWereSet;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Event\NodeAggregateWasRemoved;
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
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindAncestorNodesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdsWithDimensionSpacePoints;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdWithDimensionSpacePoints;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
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

        $contentGraph = $this->contentGraphReadModel->getContentGraph($eventInstance->getWorkspaceName());

        $dimensionSpacePoints = match ($eventInstance::class) {
            NodeAggregateWasMoved::class => $eventInstance->succeedingSiblingsForCoverage->toDimensionSpacePointSet(),
            NodePropertiesWereSet::class => $eventInstance->affectedDimensionSpacePoints,
            NodeAggregateWithNodeWasCreated::class => $eventInstance->succeedingSiblingsForCoverage->toDimensionSpacePointSet(),
            NodeReferencesWereSet::class => $eventInstance->affectedSourceOriginDimensionSpacePoints->toDimensionSpacePointSet(),
            SubtreeWasTagged::class,
            SubtreeWasUntagged::class => $eventInstance->affectedDimensionSpacePoints,
            NodeAggregateWasRemoved::class => $eventInstance->affectedCoveredDimensionSpacePoints,
            NodeAggregateTypeWasChanged::class => null,
            NodePeerVariantWasCreated::class => $eventInstance->peerOrigin->toDimensionSpacePoint(),
            NodeGeneralizationVariantWasCreated::class => $eventInstance->generalizationOrigin->toDimensionSpacePoint(),
            default => null
        };

        if ($dimensionSpacePoints === null) {
            // todo change node type
            return;
        }

        $nodeOrAncestorIsSoftRemoved = false;
        foreach ($dimensionSpacePoints as $dimensionSpacePoint) {
            $subgraph = $contentGraph->getSubgraph($dimensionSpacePoint, VisibilityConstraints::withoutRestrictions());
            $node = $subgraph->findNodeById($eventInstance->getNodeAggregateId());
            $nodeOrAncestorIsSoftRemoved = $node?->tags->contain(SubtreeTag::removed()) === true;
            if ($nodeOrAncestorIsSoftRemoved) {
                break;
            }
        }

        if (!$nodeOrAncestorIsSoftRemoved) {
            return;
        }

        // todo use findAncestorNodes for each dimension
        $ancestorNodes = $subgraph->findAncestorNodes($eventInstance->getNodeAggregateId(), FindAncestorNodesFilter::create())->filter(fn (Node $node) => $node->tags->withoutInherited()->contain(SubtreeTag::removed()));
        $this->objectionRepository->addObjection(
            $this->contentRepositoryId,
            $eventInstance->getWorkspaceName(),
            NodeAggregateIdsWithDimensionSpacePoints::fromArray($ancestorNodes->map(fn (Node $node) => NodeAggregateIdWithDimensionSpacePoints::create($node->aggregateId, $dimensionSpacePoints)))
        );
    }

    public function onAfterBatchCompleted(): void
    {
    }

    public function onAfterCatchUp(): void
    {
    }
}
