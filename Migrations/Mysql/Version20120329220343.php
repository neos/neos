<?php

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adjust default values to NOT NULL unless allowed in model.
 */
class Version20120329220343 extends AbstractMigration
{
    /**
     * @param Schema $schema
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform));

        $this->addSql("ALTER TABLE typo3_typo3_domain_model_domain CHANGE hostpattern hostpattern VARCHAR(255) NOT NULL");
        $this->addSql("ALTER TABLE typo3_typo3_domain_model_site CHANGE name name VARCHAR(255) NOT NULL, CHANGE nodename nodename VARCHAR(255) NOT NULL, CHANGE state state INT NOT NULL, CHANGE siteresourcespackagekey siteresourcespackagekey VARCHAR(255) NOT NULL");
        $this->addSql("ALTER TABLE typo3_typo3_domain_model_userpreferences CHANGE preferences preferences LONGTEXT NOT NULL COMMENT '(DC2Type:array)'");
    }

    /**
     * @param Schema $schema
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform));

        $this->addSql("ALTER TABLE typo3_typo3_domain_model_domain CHANGE hostpattern hostpattern VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE typo3_typo3_domain_model_site CHANGE name name VARCHAR(255) DEFAULT NULL, CHANGE nodename nodename VARCHAR(255) DEFAULT NULL, CHANGE state state INT DEFAULT NULL, CHANGE siteresourcespackagekey siteresourcespackagekey VARCHAR(255) DEFAULT NULL");
        $this->addSql("ALTER TABLE typo3_typo3_domain_model_userpreferences CHANGE preferences preferences LONGTEXT DEFAULT NULL COMMENT '(DC2Type:array)'");
    }
}
