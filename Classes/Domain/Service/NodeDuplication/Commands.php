<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\Service\NodeDuplication;

use Neos\ContentRepository\Core\CommandHandler\CommandInterface;

/**
 * @implements \IteratorAggregate<CommandInterface>
 */
final readonly class Commands implements \IteratorAggregate, \Countable
{
    /** @var array<CommandInterface> */
    private array $items;

    private function __construct(
        CommandInterface ...$items
    ) {
        $this->items = $items;
    }

    public static function create(CommandInterface ...$items): self
    {
        return new self(...$items);
    }

    public static function createEmpty(): self
    {
        return new self();
    }

    /** @param array<CommandInterface> $array */
    public static function fromArray(array $array): self
    {
        return new self(...$array);
    }

    public function append(CommandInterface $command): self
    {
        return new self(...[...$this->items, $command]);
    }

    public function merge(self $other): self
    {
        return new self(...$this->items, ...$other->items);
    }

    public function getIterator(): \Traversable
    {
        yield from $this->items;
    }

    public function count(): int
    {
        return count($this->items);
    }
}
