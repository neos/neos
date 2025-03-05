<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Neos\ContentRepository\Core\CommandHandler\CommandHookInterface;
use Neos\ContentRepository\Core\Factory\CommandHookFactoryInterface;
use Neos\ContentRepository\Core\Factory\CommandHooksFactoryDependencies;
use Neos\Flow\Annotations as Flow;

#[Flow\Proxy(false)]
final readonly class SoftRemovalConstraintsCommandHookFactory implements CommandHookFactoryInterface
{
    public function build(CommandHooksFactoryDependencies $commandHooksFactoryDependencies): CommandHookInterface
    {
        return new SoftRemovalConstraints($commandHooksFactoryDependencies->contentGraphReadModel);
    }
}
