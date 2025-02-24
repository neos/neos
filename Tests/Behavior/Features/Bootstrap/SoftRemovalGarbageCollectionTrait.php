<?php

declare(strict_types=1);

use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\Neos\Domain\SoftRemoval\SoftRemovalGarbageCollector;

/**
 * @internal only for behat tests within the Neos.Neos package
 */
trait SoftRemovalGarbageCollectionTrait
{
    /**
     * @When soft removal garbage collection is run for content repository :contentRepositoryId
     */
    public function softRemovalGarbageCollectionIsRunForContentRepository(string $contentRepositoryId): void
    {
        $garbageCollector = new SoftRemovalGarbageCollector();

        $garbageCollector->run(ContentRepositoryId::fromString($contentRepositoryId));
    }
}
