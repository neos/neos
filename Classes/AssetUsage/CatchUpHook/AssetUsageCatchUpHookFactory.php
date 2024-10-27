<?php

declare(strict_types=1);

namespace Neos\Neos\AssetUsage\CatchUpHook;

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
use Neos\Neos\AssetUsage\Service\AssetUsageIndexingService;

/**
 * @implements CatchUpHookFactoryInterface<ContentGraphReadModelInterface>
 */
class AssetUsageCatchUpHookFactory implements CatchUpHookFactoryInterface
{
    public function __construct(
        private AssetUsageIndexingService $assetUsageIndexingService
    ) {
    }

    public function build(ContentRepositoryId $contentRepositoryId, ProjectionStateInterface $projectionState): AssetUsageCatchUpHook
    {
        return new AssetUsageCatchUpHook(
            $contentRepositoryId,
            $projectionState,
            $this->assetUsageIndexingService
        );
    }
}
