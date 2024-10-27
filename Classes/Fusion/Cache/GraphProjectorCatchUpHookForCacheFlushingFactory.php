<?php

declare(strict_types=1);

namespace Neos\Neos\Fusion\Cache;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Core\Projection\CatchUpHookFactoryInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\Projection\ProjectionStateInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;

/**
 * @implements CatchUpHookFactoryInterface<ContentGraphReadModelInterface>
 */
class GraphProjectorCatchUpHookForCacheFlushingFactory implements CatchUpHookFactoryInterface
{
    public function __construct(
        private readonly ContentCacheFlusher $contentCacheFlusher
    ) {
    }

    public function build(ContentRepositoryId $contentRepositoryId, ProjectionStateInterface $projectionState): GraphProjectorCatchUpHookForCacheFlushing
    {
        return new GraphProjectorCatchUpHookForCacheFlushing(
            $contentRepositoryId,
            $projectionState,
            $this->contentCacheFlusher
        );
    }
}
