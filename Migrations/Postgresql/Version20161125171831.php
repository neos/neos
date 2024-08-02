<?php

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Rename node names in neos_contentrepository_domain_model_nodedata
 */
class Version20161125171831 extends AbstractMigration
{
    /**
     * @param Schema $schema
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform), 'Migration can only be executed safely on "postgresql".');

        $this->addSql("UPDATE neos_neos_eventlog_domain_model_event SET dtype = REPLACE(dtype, 'typo3_neos_', 'neos_neos_')");
    }

    /**
     * @param Schema $schema
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform), 'Migration can only be executed safely on "postgresql".');

        $this->addSql("UPDATE neos_neos_eventlog_domain_model_event SET dtype = REPLACE(dtype, 'neos_neos_', 'typo3_neos_')");
    }
}
