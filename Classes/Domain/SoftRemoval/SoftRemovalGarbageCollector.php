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
        private ContentRepositoryRegistry $contentRepositoryRegistry
    ) {
    }

    public function run(ContentRepositoryId $contentRepositoryId): void
    {
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $liveSoftRemovals = $this->findNodeAggregatesInWorkspaceByExplicitRemovedTag($contentRepository->getContentGraph(WorkspaceName::forLive()));

        $softRemovalsAcrossWorkspaces = $this->subtractFromSoftRemovalsWhichAreNotSoftRemovedInOtherWorkspaces($liveSoftRemovals, $contentRepository);

        foreach ($softRemovalsAcrossWorkspaces as $softRemoval) {
            foreach ($softRemoval->dimensionSpacePointSet as $dimensionSpacePoint) {
                try {
                    $contentRepository->handle(RemoveNodeAggregate::create(
                        WorkspaceName::forLive(),
                        $softRemoval->nodeAggregateId,
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
    private function subtractFromSoftRemovalsWhichAreNotSoftRemovedInOtherWorkspaces(NodeAggregateIdsWithDimensionSpacePoints $liveSoftRemovals, ContentRepository $contentRepository): NodeAggregateIdsWithDimensionSpacePoints
    {
        /** @var array<string,NodeAggregateIdWithDimensionSpacePoints> $softRemovedNodesAcrossWorkspaces */
        $softRemovedNodesAcrossWorkspaces = array_combine(
            array_map(fn (NodeAggregateIdWithDimensionSpacePoints $node) => $node->nodeAggregateId->value, iterator_to_array($liveSoftRemovals)),
            iterator_to_array($liveSoftRemovals),
        );

        foreach ($contentRepository->findWorkspaces() as $workspace) {
            if ($workspace->isRootWorkspace()) {
                // todo ignore base workspaces of Live!
                continue;
            }
            $contentGraph = $contentRepository->getContentGraph($workspace->workspaceName);

            foreach ($softRemovedNodesAcrossWorkspaces as $key => $softRemovedNode) {
                $nodeAggregateInWorkspace = $contentGraph->findNodeAggregateById($softRemovedNode->nodeAggregateId);
                if ($nodeAggregateInWorkspace === null) {
                    continue;
                }
                $softDeletedDimensionsInWorkspace = $nodeAggregateInWorkspace->getDimensionSpacePointsTaggedWith(SubtreeTag::removed());
                $notSoftDeletedDimensionsInWorkspace = $nodeAggregateInWorkspace->coveredDimensionSpacePoints->getDifference($softDeletedDimensionsInWorkspace);

                $softRemovedNodesAcrossWorkspaces[$key] = NodeAggregateIdWithDimensionSpacePoints::create(
                    $softRemovedNode->nodeAggregateId,
                    $softRemovedNode->dimensionSpacePointSet->getDifference($notSoftDeletedDimensionsInWorkspace)
                );
            }
        }

        return NodeAggregateIdsWithDimensionSpacePoints::fromArray($softRemovedNodesAcrossWorkspaces);
    }

    private function findNodeAggregatesInWorkspaceByExplicitRemovedTag(ContentGraphInterface $contentGraph): NodeAggregateIdsWithDimensionSpacePoints
    {
        // todo super expensive, filter via sql!
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
