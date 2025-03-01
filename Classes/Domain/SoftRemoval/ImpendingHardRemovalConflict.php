<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;

/** @internal only to be used for hard removal conflict handling */
final readonly class ImpendingHardRemovalConflict
{
    private function __construct(
        public NodeAggregateId $nodeAggregateId,
        public DimensionSpacePointSet $dimensionSpacePointSet
    ) {
    }

    public static function create(
        NodeAggregateId $nodeAggregateId,
        DimensionSpacePointSet $dimensionSpacePointSet
    ): self {
        return new self($nodeAggregateId, $dimensionSpacePointSet);
    }
}
