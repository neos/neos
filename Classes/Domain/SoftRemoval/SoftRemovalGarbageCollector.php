<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\Feature\NodeRemoval\Command\RemoveNodeAggregate;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\Filter\FindRootNodeAggregatesFilter;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregate;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdsWithDimensionSpacePoints;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdWithDimensionSpacePoints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
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
 * 3) if there are no objections, execute the hard deletion on the root workspace(s)
 * 4) the hard deletion will then propagate to the other workspace automatically via rebase
 *
 * @internal safe to run at any time, but manual runs should be unnecessary
 */
final readonly class SoftRemovalGarbageCollector
{
    public function __construct(
        private ContentRepositoryRegistry $contentRepositoryRegistry,
        private SoftRemovalObjectionRepository $objectionRepository
    ) {
    }

    public function run(ContentRepositoryId $contentRepositoryId): void
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $liveSoftRemovals = $this->findNodeAggregatesInWorkspaceByExplicitRemovedTag($contentRepository->getContentGraph(WorkspaceName::forLive()));

        $softRemovalsAcrossWorkspaces = $this->subtractNodesWhichAreNotSoftRemovedInOtherWorkspaces($liveSoftRemovals, $contentRepository);

        $softRemovalsWithoutObjections = $this->subtractObjections($softRemovalsAcrossWorkspaces, $contentRepositoryId);

        foreach ($softRemovalsWithoutObjections as $softRemovedNode) {
            foreach ($softRemovedNode->dimensionSpacePointSet as $dimensionSpacePoint) {
                try {
                    $contentRepository->handle(RemoveNodeAggregate::create(
                        WorkspaceName::forLive(),
                        $softRemovedNode->nodeAggregateId,
                        $dimensionSpacePoint,
                        NodeVariantSelectionStrategy::STRATEGY_ALL_SPECIALIZATIONS
                    ));
                } catch (\Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregateCurrentlyDoesNotExist) {
                    // todo avoid beforehand
                    // a) grouping dimensions so the later STRATEGY_ALL_SPECIALIZATIONS will do it in reverse
                    // b) remove parents first and then a possible child removal, not in reverse
                }
            }
        }
    }

    /**
     * If a workspace is outdated and does not have the soft deletion yet, but knows the node we cannot safely remove the node as
     * the user can still make changes to that node or children any time in the future - or might have done so already.
     * Heavily outdated workspaces might not even know the node yet and thus impose NO conflict.
     */
    private function subtractNodesWhichAreNotSoftRemovedInOtherWorkspaces(NodeAggregateIdsWithDimensionSpacePoints $liveSoftRemovals, ContentRepository $contentRepository): NodeAggregateIdsWithDimensionSpacePoints
    {
        $softRemovedNodesAcrossWorkspaces = $liveSoftRemovals;

        foreach ($contentRepository->findWorkspaces() as $workspace) {
            if ($workspace->isRootWorkspace()) {
                // todo ignore base workspaces of Live!
                continue;
            }
            $contentGraph = $contentRepository->getContentGraph($workspace->workspaceName);

            foreach ($softRemovedNodesAcrossWorkspaces as $softRemovedNode) {
                // todo use in () query instead of many
                $nodeAggregateInWorkspace = $contentGraph->findNodeAggregateById($softRemovedNode->nodeAggregateId);
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
     * Workspace that are already synchronised with live know the soft deletions. This means that all pending changes have been rebased
     * on live and at the time of the rebase each of the events was aware of soft deletion. The objections are remembered via hook and will be checked here.
     */
    private function subtractObjections(NodeAggregateIdsWithDimensionSpacePoints $softRemovals, ContentRepositoryId $contentRepositoryId): NodeAggregateIdsWithDimensionSpacePoints
    {
        $softRemovalsWithoutObjections = $softRemovals;

        $objections = $this->objectionRepository->getAllObjections($contentRepositoryId);

        foreach ($softRemovalsWithoutObjections as $softRemovedNode) {
            $objection = $objections->get($softRemovedNode->nodeAggregateId);
            if ($objection === null) {
                continue;
            }
            $softRemovalsWithoutObjections = $softRemovalsWithoutObjections->with(NodeAggregateIdWithDimensionSpacePoints::create(
                $softRemovedNode->nodeAggregateId,
                $softRemovedNode->dimensionSpacePointSet->getDifference($objection->dimensionSpacePointSet)
            ));
        }

        return $softRemovalsWithoutObjections;
    }

    private function findNodeAggregatesInWorkspaceByExplicitRemovedTag(ContentGraphInterface $contentGraph): NodeAggregateIdsWithDimensionSpacePoints
    {
        // todo super expensive, filter via sql! getNodeAggregatesTaggedBy
        $softRemovedNodes = [];

        /** @var array<NodeAggregate> $stack */
        $stack = iterator_to_array($contentGraph->findRootNodeAggregates(FindRootNodeAggregatesFilter::create()));
        while ($stack !== []) {
            $nodeAggregate = array_shift($stack);
            $softRemovedDimensions = $nodeAggregate->getDimensionSpacePointsTaggedWith(SubtreeTag::removed());
            if (!$softRemovedDimensions->isEmpty()) {
                $softRemovedNodes[] = NodeAggregateIdWithDimensionSpacePoints::create(
                    $nodeAggregate->nodeAggregateId,
                    $softRemovedDimensions
                );
            }
            $stack = [...$stack, ...iterator_to_array($contentGraph->findChildNodeAggregates($nodeAggregate->nodeAggregateId))];
        }

        return NodeAggregateIdsWithDimensionSpacePoints::fromArray($softRemovedNodes);
    }
}
