<?php

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename node names in neos_contentrepository_domain_model_nodedata
 */
class Version20161125124749 extends AbstractMigration
{
    /**
     * @param Schema $schema
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform), 'Migration can only be executed safely on "mysql".');

        $schemaManager = $this->connection->createSchemaManager();
        $hasTables = $schemaManager->tablesExist(['neos_contentrepository_domain_model_nodedata']);
        if ($hasTables) {
            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET nodetype = REPLACE(nodetype, 'TYPO3.Neos:', 'Neos.Neos:')");

            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET properties = REPLACE(properties, 'TYPO3\\\\\\\\Media\\\\\\\\', 'Neos\\\\\\\\Media\\\\\\\\')");
            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET properties = REPLACE(properties, 'TYPO3\\\\\\\\Flow\\\\\\\\', 'Neos\\\\\\\\Flow\\\\\\\\')");
            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET properties = REPLACE(properties, 'TYPO3\\\\\\\\Neos\\\\\\\\', 'Neos\\\\\\\\Neos\\\\\\\\')");
            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET properties = REPLACE(properties, 'TYPO3\\\\\\\\Party\\\\\\\\', 'Neos\\\\\\\\Party\\\\\\\\')");
            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET properties = REPLACE(properties, 'TYPO3\\\\\\\\TYPO3CR\\\\\\\\', 'Neos\\\\\\\\ContentRepository\\\\\\\\')");
        }
    }

    /**
     * @param Schema $schema
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform), 'Migration can only be executed safely on "mysql".');

        $schemaManager = $this->connection->createSchemaManager();
        $hasTables = $schemaManager->tablesExist(['neos_contentrepository_domain_model_nodedata']);
        if ($hasTables) {
            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET nodetype = REPLACE(nodetype, 'Neos.Neos:', 'TYPO3.Neos:')");

            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET properties = REPLACE(properties, 'Neos\\\\\\\\Media\\\\\\\\', 'TYPO3\\\\\\\\Media\\\\\\\\')");
            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET properties = REPLACE(properties, 'Neos\\\\\\\\Flow\\\\\\\\', 'TYPO3\\\\\\\\Flow\\\\\\\\')");
            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET properties = REPLACE(properties, 'Neos\\\\\\\\Neos\\\\\\\\', 'TYPO3\\\\\\\\Neos\\\\\\\\')");
            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET properties = REPLACE(properties, 'Neos\\\\\\\\Party\\\\\\\\', 'TYPO3\\\\\\\\Party\\\\\\\\')");
            $this->addSql("UPDATE neos_contentrepository_domain_model_nodedata SET properties = REPLACE(properties, 'Neos\\\\\\\\ContentRepository\\\\\\\\', 'TYPO3\\\\\\\\TYPO3CR\\\\\\\\')");
        }
    }
}
