<?php

declare(strict_types=1);

use Behat\Gherkin\Node\TableNode;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdWithDimensionSpacePoints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
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

        $actualConflictsTable = array_map(static fn (NodeAggregateIdWithDimensionSpacePoints $conflict) => [
            'nodeAggregateId' => $conflict->nodeAggregateId->value,
            'dimensionSpacePoints' => $conflict->dimensionSpacePointSet->toJson(),
        ], iterator_to_array($actualConflicts));

        $expectedConflictsWithNormalisedJson = array_map(
            fn (array $row) => [...$row, 'dimensionSpacePoints' => DimensionSpacePointSet::fromJsonString($row['dimensionSpacePoints'])->toJson()],
            $payloadTable->getHash()
        );

        Assert::assertSame($expectedConflictsWithNormalisedJson, $actualConflictsTable);
    }

    /**
     * @When soft removal garbage collection is run for content repository :contentRepositoryId
     */
    public function softRemovalGarbageCollectionIsRunForContentRepository(string $contentRepositoryId): void
    {
        $this->getObject(SoftRemovalGarbageCollector::class)->run(ContentRepositoryId::fromString($contentRepositoryId));
    }

    /**
     * @BeforeScenario
     */
    final public function pruneImpendingHardRemovalConflicts(): void
    {
        foreach (static::$alreadySetUpContentRepositories as $contentRepositoryId) {
            $this->getObject(ImpendingHardRemovalConflictRepository::class)->pruneConflictsForContentRepository($contentRepositoryId);
        }
    }
}
