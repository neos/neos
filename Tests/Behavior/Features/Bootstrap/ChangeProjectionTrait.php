<?php
declare(strict_types=1);

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */


use Behat\Gherkin\Node\TableNode;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\SharedModel\Workspace\ContentStreamId;
use Neos\Neos\PendingChangesProjection\Change;
use Neos\Neos\PendingChangesProjection\ChangeFinder;
use PHPUnit\Framework\Assert;

/**
 * Step implementations for tests inside Neos.Neos
 *
 * @internal only for behat tests within the Neos.Neos package
 */
trait ChangeProjectionTrait
{
    /**
     * @template T of object
     * @param class-string<T> $className
     *
     * @return T
     */
    abstract private function getObject(string $className): object;

    /**
     * @Then I expect the ChangeProjection to have the following changes in :contentStreamId:
     */
    public function iExpectTheChangeProjectionToHaveTheFollowingChangesInContentStream(TableNode $table, string $contentStreamId)
    {
        $changeFinder = $this->currentContentRepository->projectionState(ChangeFinder::class);
        $changes = iterator_to_array($changeFinder->findByContentStreamId(ContentStreamId::fromString($contentStreamId)));

        $actualChangesTable = array_map(static fn (Change $change) => [
            'nodeAggregateId' => $change->nodeAggregateId->value,
            'created' => (string)(int)$change->created,
            'changed' => (string)(int)$change->changed,
            'moved' => (string)(int)$change->moved,
            'deleted' => (string)(int)$change->deleted,
            'originDimensionSpacePoint' => $change->originDimensionSpacePoint?->toJson(),
        ], iterator_to_array($changes));

        $expectedChangesWithNormalisedJson = array_map(
            fn (array $row) => [
                ...$row,
                'originDimensionSpacePoint' => ($originDimensionSpacePoint = json_decode($row['originDimensionSpacePoint'], true)) !== null
                    ? OriginDimensionSpacePoint::fromArray($originDimensionSpacePoint)->toJson()
                    : null,
            ],
            $table->getHash()
        );

        // assertEqualsCanonicalizing removes keys by using sort recursively that's why we sort manually
        array_walk($actualChangesTable, 'ksort');
        array_walk($expectedChangesWithNormalisedJson, 'ksort');
        sort($actualChangesTable);
        sort($expectedChangesWithNormalisedJson);

        Assert::assertEquals($expectedChangesWithNormalisedJson, $actualChangesTable, 'Mismatch of changes');
    }

    /**
     * @Then I expect the ChangeProjection to have no changes in :contentStreamId
     */
    public function iExpectTheChangeProjectionToHaveNoChangesInContentStream(string $contentStreamId)
    {
        $changeFinder = $this->currentContentRepository->projectionState(ChangeFinder::class);
        $changes = iterator_to_array($changeFinder->findByContentStreamId(ContentStreamId::fromString($contentStreamId)));

        Assert::assertEmpty($changes, "No changes expected, got: " . json_encode($changes, JSON_PRETTY_PRINT));
    }
}
