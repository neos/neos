<?php

declare(strict_types=1);

namespace Neos\Neos\FrontendRouting\CatchUpHook;

use Neos\ContentRepository\Core\Projection\CatchUpHookFactoryInterface;
use Neos\ContentRepository\Core\Projection\CatchUpHookInterface;
use Neos\ContentRepository\Core\Projection\ProjectionStateInterface;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\Flow\Mvc\Routing\RouterCachingService;
use Neos\Neos\FrontendRouting\Projection\DocumentUriPathFinder;

/**
 * @implements CatchUpHookFactoryInterface<DocumentUriPathFinder>
 */
final class RouterCacheHookFactory implements CatchUpHookFactoryInterface
{
    public function __construct(
        protected readonly RouterCachingService $routerCachingService,
    ) {
    }

    public function build(ContentRepositoryId $contentRepositoryId, ProjectionStateInterface $projectionState): CatchUpHookInterface
    {
        return new RouterCacheHook(
            $projectionState,
            $this->routerCachingService
        );
    }
}
