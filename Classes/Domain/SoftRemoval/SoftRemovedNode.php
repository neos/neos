<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;

/** @internal */
final readonly class SoftRemovedNode
{
    private function __construct(
        public NodeAggregateId $nodeAggregateId,
        public DimensionSpacePointSet $removedDimensionSpacePoints,
        public DimensionSpacePointSet $conflictingDimensionSpacePoints
    ) {
    }

    public static function create(
        NodeAggregateId $nodeAggregateId,
        DimensionSpacePointSet $removedDimensionSpacePoints,
    ): self {
        return new self($nodeAggregateId, $removedDimensionSpacePoints, DimensionSpacePointSet::fromArray([]));
    }

    public function withConflictingDimensionSpacePoints(DimensionSpacePointSet $conflictingDimensionSpacePoints): self
    {
        return new self($this->nodeAggregateId, $this->removedDimensionSpacePoints, $conflictingDimensionSpacePoints);
    }
}
