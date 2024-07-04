<?php

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Change all existing serialized ImageVariants to arrays to be picked up and converted by the matching ContentRepository migration.
 */
class Version20141017174559 extends AbstractMigration
{
    /**
     * @param Schema $schema
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql");
        $schemaManager = $this->connection->createSchemaManager();
        $hasTables = $schemaManager->tablesExist(['typo3_typo3cr_domain_model_nodedata']);
        if ($hasTables) {
            $this->addSql("UPDATE typo3_typo3cr_domain_model_nodedata SET properties = REPLACE(properties, 'O:37:\"TYPO3\\\\Media\\\\Domain\\\\Model\\\\ImageVariant\"', 'a');");
        }
    }

    /**
     * @param Schema $schema
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != "mysql");
        $this->write('The conversion of nodes to the new resource management cannot be reverted with a database migration.');
    }
}
