<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SubtreeTagging;

use Neos\ContentRepository\Core\CommandHandler\CommandHookInterface;
use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\CommandHandler\Commands;
use Neos\ContentRepository\Core\EventStore\PublishedEvents;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Command\TagSubtree;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregateIsTethered;
use Neos\Flow\Annotations as Flow;

/**
 * Additional Neos' specific content repository constraints for subtree tagging
 *
 * The content repository imposes no special constraints for any subtree tag
 *
 * We herby prevent the disabling of tethered nodes
 *
 * @Flow\Proxy(false)
 * @internal
 */
final readonly class NeosSubtreeTaggingConstraintChecks implements CommandHookInterface
{
    public function __construct(
        private ContentGraphReadModelInterface $contentGraphReadModel
    ) {
    }

    public function onBeforeHandle(CommandInterface $command): CommandInterface
    {
        if (!$command instanceof TagSubtree || !$command->tag->equals(NeosSubtreeTag::disabled())) {
            return $command;
        }

        $nodeAggregate = $this->contentGraphReadModel->getContentGraph($command->workspaceName)->findNodeAggregateById($command->nodeAggregateId);
        if ($nodeAggregate?->classification->isTethered()) {
            throw new NodeAggregateIsTethered(
                sprintf('Node aggregate "%s" is classified as tethered and must not be disabled.', $nodeAggregate->nodeAggregateId->value),
                1741161426
            );
        }

        return $command;
    }

    public function onAfterHandle(CommandInterface $command, PublishedEvents $events): Commands
    {
        return Commands::createEmpty();
    }
}
