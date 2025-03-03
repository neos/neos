<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Neos\ContentRepository\Core\Feature\NodeRemoval\Command\RemoveNodeAggregate;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Command\TagSubtree;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Dto\SubtreeTag;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\Neos\Domain\Service\WorkspacePublishingService;

/**
 * Content repository subtree tag which is used to denote that a node is soft removed
 *
 * Issuing 'hard' removals via {@see RemoveNodeAggregate} on a non-live workspace ist not desired in Neos
 * and comes with complications:
 *
 * - Hard removals destroy all hierarchy information immediately, making it impossible to locate where a removal took place.
 *   A special case imposes the deletion of newly created nodes.
 *   Associating content changes with its document is important when publishing via {@see WorkspacePublishingService::publishChangesInDocument()}
 *
 * - Hard removals easily cause conflicts:
 *   The removal of a selected node might not be published in that scope if a node was moved outwards.
 *   The rebase will cause conflicts if changes were made in a hierarchy of removed nodes, like moving nodes outwards.
 *
 * Instead, tagging a node / its subtree via {@see TagSubtree} will mark it as removed in Neos.
 *
 *     $contentRepository->handle(TagSubtree::create(
 *         $node->workspaceName,
 *         $node->aggregateId,
 *         $node->dimensionSpacePoint,
 *         NodeVariantSelectionStrategy::STRATEGY_ALL_SPECIALIZATIONS,
 *         SoftRemovedTag::getSubtreeTag()
 *     ));
 *
 * Nodes tagged as removed will not show up in the frontend rendering nor in the backend by default.
 * Only subtracting this removed tag from the {@see VisibilityConstraints} or operating on the node aggregates directly
 * will make soft removed nodes available.
 *
 * @Flow\Proxy(false)
 */
final readonly class SoftRemovedTag
{
    private const IDENTIFIER = 'removed';

    public static function getSubtreeTag(): SubtreeTag
    {
        return SubtreeTag::fromString(self::IDENTIFIER);
    }

    public static function isRemovedSubtreeTag(SubtreeTag $subtreeTag): bool
    {
        return $subtreeTag->value === self::IDENTIFIER;
    }
}
