<?php

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adjust table names to the renaming of TYPO3.Neos to Neos.Neos.
 */
class Version20161125093800 extends AbstractMigration
{
    /**
     * @param Schema $schema
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform), 'Migration can only be executed safely on "mysql".');

        $this->addSql('RENAME TABLE typo3_neos_domain_model_domain TO neos_neos_domain_model_domain');
        $this->addSql('RENAME TABLE typo3_neos_domain_model_site TO neos_neos_domain_model_site');
        $this->addSql('RENAME TABLE typo3_neos_domain_model_user TO neos_neos_domain_model_user');
        $this->addSql('RENAME TABLE typo3_neos_domain_model_userpreferences TO neos_neos_domain_model_userpreferences');
        $this->addSql('RENAME TABLE typo3_neos_eventlog_domain_model_event TO neos_neos_eventlog_domain_model_event');
    }

    /**
     * @param Schema $schema
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform), 'Migration can only be executed safely on "mysql".');

        $this->addSql('RENAME TABLE neos_neos_domain_model_domain TO typo3_neos_domain_model_domain');
        $this->addSql('RENAME TABLE neos_neos_domain_model_site TO typo3_neos_domain_model_site');
        $this->addSql('RENAME TABLE neos_neos_domain_model_user TO typo3_neos_domain_model_user');
        $this->addSql('RENAME TABLE neos_neos_domain_model_userpreferences TO typo3_neos_domain_model_userpreferences');
        $this->addSql('RENAME TABLE neos_neos_eventlog_domain_model_event TO typo3_neos_eventlog_domain_model_event');
    }
}
