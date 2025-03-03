<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\Service;

use Neos\ContentRepository\Core\Feature\NodeDisabling\Command\DisableNodeAggregate;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTags;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentSubgraphInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\Neos\Domain\SoftRemoval\SoftRemovedTag;

/**
 * The visibility constraints define a context in which the content graph is accessed.
 *
 * By default, Neos provides two kinds of subtree tags:
 *
 * - `disabled` {@see SubtreeTag::disabled()} which is used when disabling a node via {@see DisableNodeAggregate}
 * - `removed` {@see SoftRemovedTag} which is used when soft removing a node
 *
 * To control which nodes will be queried via the {@see ContentSubgraphInterface}, the visibility constraints can be used.
 *
 * This allows for the "frontend" rendering to have `disabled` and `removed` nodes excluded when traversing {@see NeosVisibilityConstraints::frontend()}.
 * While for the rendering in the backend `disabled` nodes are shown to the editor - though not `removed` nodes {@see NeosVisibilityConstraints::withoutRemoved()}.
 *
 * To access all nodes, including soft removed nodes, please use {@see VisibilityConstraints::createEmpty()}
 *
 * Specifying the visibility constraints manually is only necessary when breaking away from the by default provided
 * constraints via {@see ContentRepository::getContentSubgraph()}. Circumventing the neos auth provider leads to no ReadNodePrivilege's being evaluated.
 * All custom tagged nodes are visible at all times unless excluded via {@see VisibilityConstraints::withAddedSubtreeTag()}
 *
 * @api
 */
final class NeosVisibilityConstraints
{
    private function __construct()
    {
        // no instances
    }

    /**
     * Default constraints for frontend rendering, ensuring that neither disabled nor soft removed nodes are visible
     */
    public static function frontend(): VisibilityConstraints
    {
        return VisibilityConstraints::fromTagConstraints(SubtreeTags::create(
            SubtreeTag::disabled(),
            SoftRemovedTag::getSubtreeTag()
        ));
    }

    /**
     * Default constraints for the backend and cli, ensuring that disabled are visible, but not soft removed nodes
     */
    public static function withoutRemoved(): VisibilityConstraints
    {
        return VisibilityConstraints::fromTagConstraints(SubtreeTags::create(
            SoftRemovedTag::getSubtreeTag()
        ));
    }
}
