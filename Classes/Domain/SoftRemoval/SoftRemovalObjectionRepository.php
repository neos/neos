<?php

declare(strict_types=1);

namespace Neos\Neos\Domain\SoftRemoval;

use Doctrine\DBAL\Connection;
use Neos\ContentRepository\Core\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdsWithDimensionSpacePoints;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodeAggregateIdWithDimensionSpacePoints;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;

/** @internal */
final readonly class SoftRemovalObjectionRepository
{
    private const TABLE_NAME_OBJECTION = 'neos_neos_soft_removal_objection';

    public function __construct(
        private Connection $dbal
    ) {
    }

    public function getAllObjections(ContentRepositoryId $contentRepositoryId): NodeAggregateIdsWithDimensionSpacePoints
    {
        $table = self::TABLE_NAME_OBJECTION;
        $query = <<<SQL
            SELECT
                node_aggregate_id, dimension_space_points
            FROM
                {$table}
            WHERE
                content_repository_id = :contentRepositoryId
        SQL;

        $rows = $this->dbal->fetchAllAssociative($query, [
            'contentRepositoryId' => $contentRepositoryId->value,
        ]);

        /** @var array<string, NodeAggregateIdWithDimensionSpacePoints> $objections */
        $objections = [];
        foreach ($rows as $row) {
            $nodeAggregateId = NodeAggregateId::fromString($row['node_aggregate_id']);

            if (!array_key_exists($nodeAggregateId->value, $objections)) {
                $objections[$nodeAggregateId->value] = NodeAggregateIdWithDimensionSpacePoints::create(
                    $nodeAggregateId,
                    DimensionSpacePointSet::fromJsonString($row['dimension_space_points'])
                );
            } else {
                $objections[$nodeAggregateId->value] = NodeAggregateIdWithDimensionSpacePoints::create(
                    $nodeAggregateId,
                    $objections[$nodeAggregateId->value]->dimensionSpacePointSet->getUnion(DimensionSpacePointSet::fromJsonString($row['dimension_space_points']))
                );
            }
        }

        return NodeAggregateIdsWithDimensionSpacePoints::fromArray($objections);
    }

    public function addObjection(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName, NodeAggregateIdsWithDimensionSpacePoints $softRemovals): void
    {
        $table = self::TABLE_NAME_OBJECTION;
        $query = <<<SQL
            SELECT
                dimension_space_points
            FROM
                {$table}
            WHERE
                content_repository_id = :contentRepositoryId
                AND workspace_name = :workspaceName
                AND node_aggregate_id = :nodeAggregateId
        SQL;

        foreach ($softRemovals as $softRemoval) {
            if ($softRemoval->dimensionSpacePointSet->isEmpty()) {
                continue;
            }
            $row = $this->dbal->fetchAssociative($query, [
                'contentRepositoryId' => $contentRepositoryId->value,
                'workspaceName' => $workspaceName->value,
                'nodeAggregateId' => $softRemoval->nodeAggregateId->value
            ]);
            if ($row === false) {
                $this->dbal->insert($table, [
                    'content_repository_id' => $contentRepositoryId->value,
                    'workspace_name' => $workspaceName->value,
                    'node_aggregate_id' => $softRemoval->nodeAggregateId->value,
                    'dimension_space_points' => $softRemoval->dimensionSpacePointSet->toJson()
                ]);
            } else {
                $updatedDimensionSpacePoints = DimensionSpacePointSet::fromJsonString($row['dimension_space_points'])->getUnion($softRemoval->dimensionSpacePointSet);
                $this->dbal->update($table, [
                    'dimension_space_points' => $updatedDimensionSpacePoints->toJson()
                ], [
                    'content_repository_id' => $contentRepositoryId->value,
                    'workspace_name' => $workspaceName->value,
                ]);
            }
        }
    }

    public function flushWorkspace(ContentRepositoryId $contentRepositoryId, WorkspaceName $workspaceName): void
    {
        $this->dbal->delete(self::TABLE_NAME_OBJECTION, [
            'content_repository_id' => $contentRepositoryId->value,
            'workspace_name' => $workspaceName->value
        ]);
    }

    public function pruneContentRepository(ContentRepositoryId $contentRepositoryId): void
    {
        $this->dbal->delete(self::TABLE_NAME_OBJECTION, [
            'content_repository_id' => $contentRepositoryId->value
        ]);
    }
}
