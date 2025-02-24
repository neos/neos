<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

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

        $liveSoftRemovalsForDimension = $this->findNodeAggregatesInWorkspaceByExplicitRemovedTag($contentRepository->getContentGraph(WorkspaceName::forLive()));

        foreach ($liveSoftRemovalsForDimension as $softRemoval) {
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
