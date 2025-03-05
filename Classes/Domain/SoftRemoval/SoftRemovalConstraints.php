<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Neos\ContentRepository\Core\CommandHandler\CommandHookInterface;
use Neos\ContentRepository\Core\CommandHandler\CommandInterface;
use Neos\ContentRepository\Core\Feature\SubtreeTagging\Command\TagSubtree;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphReadModelInterface;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregateIsRoot;
use Neos\ContentRepository\Core\SharedModel\Exception\NodeAggregateIsTethered;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Service\NeosSubtreeTag;

/**
 * Additional Neos' specific content repository constraints for subtree tagging
 *
 * The content repository imposes little constraints for subtree tagging,
 * except that disabling nodes is not allowed for tethered nodes (#4821)
 *
 * We attempt to transform the soft removals to hard removals via the {@see SoftRemovalGarbageCollector}
 * By default it is possible to tag a tethered node for example with "removed" but
 * hard removal of tethered nodes without its parent is strictly forbidden:
 *
 *     The node aggregate "nodingers-leashed-kitten" is tethered, and thus cannot be removed.
 *
 * Also root nodes can be tagged in a variety of specialisations via `STRATEGY_ALL_SPECIALIZATIONS` but can
 * only be removed in all variants. This conflicts with the dimension handling of the garbage collector.
 * Further, soft removing the sites root node is not a Neos use case, and not possible out of the box
 *
 *     Root node aggregates can only be removed by using node variant selection strategy.
 *
 * Because of this we prevent soft removals happening right from the beginning targeting such nodes.
 *
 * @Flow\Proxy(false)
 * @internal
 */
final readonly class SoftRemovalConstraints implements CommandHookInterface
{
    public function __construct(
        private ContentGraphReadModelInterface $contentGraphReadModel
    ) {
    }

    public function onBeforeHandle(CommandInterface $command): CommandInterface
    {
        if (!$command instanceof TagSubtree || !$command->tag->equals(NeosSubtreeTag::removed())) {
            return $command;
        }

        $nodeAggregate = $this->contentGraphReadModel->getContentGraph($command->workspaceName)->findNodeAggregateById($command->nodeAggregateId);
        if ($nodeAggregate?->classification->isRoot()) {
            throw new NodeAggregateIsRoot(
                sprintf('Node aggregate "%s" is classified as root and must not be soft removed.', $nodeAggregate->nodeAggregateId->value),
                1741162636
            );
        }
        if ($nodeAggregate?->classification->isTethered()) {
            throw new NodeAggregateIsTethered(
                sprintf('Node aggregate "%s" is classified as tethered and must not be soft removed.', $nodeAggregate->nodeAggregateId->value),
                1741161426
            );
        }

        return $command;
    }
}
