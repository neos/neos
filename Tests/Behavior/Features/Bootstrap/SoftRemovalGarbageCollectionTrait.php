<?php

declare(strict_types=1);

use Behat\Gherkin\Node\TableNode;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdsWithDimensionSpacePoints;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdWithDimensionSpacePoints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\Neos\Domain\SoftRemoval\SoftRemovalGarbageCollector;
use Neos\Neos\Domain\SoftRemoval\ImpendingHardRemovalConflictRepository;
use PHPUnit\Framework\Assert;

/**
 * @internal only for behat tests within the Neos.Neos package
 */
trait SoftRemovalGarbageCollectionTrait
{
    /**
     * @Then I expect the following hard removal conflicts to be impending:
     */
    public function iExpectTheFollowingHardRemovalConflictsToBeImpeding(TableNode $payloadTable): void
    {
        $actualConflicts = $this->getObject(ImpendingHardRemovalConflictRepository::class)
            ->findAllConflicts($this->currentContentRepository->id);

        $expectedConflicts = NodeAggregateIdsWithDimensionSpacePoints::fromArray(array_map(
            fn (array $row): NodeAggregateIdWithDimensionSpacePoints => NodeAggregateIdWithDimensionSpacePoints::create(
                NodeAggregateId::fromString($row['nodeAggregateId']),
                DimensionSpacePointSet::fromJsonString($row['dimensionSpacePoints'])
            ),
            $payloadTable->getHash()
        ));

        Assert::assertEquals($expectedConflicts, $actualConflicts);
    }

    /**
     * @When soft removal garbage collection is run for content repository :contentRepositoryId
     */
    public function softRemovalGarbageCollectionIsRunForContentRepository(string $contentRepositoryId): void
    {
        $garbageCollector = new SoftRemovalGarbageCollector();

        $garbageCollector->run(ContentRepositoryId::fromString($contentRepositoryId));
    }

    /**
     * @BeforeScenario
     */
    final public function pruneSoftRemovalObjections(): void
    {
        foreach (static::$alreadySetUpContentRepositories as $contentRepositoryId) {
            $this->getObject(ImpendingHardRemovalConflictRepository::class)->pruneConflictsForContentRepository($contentRepositoryId);
        }
    }
}
