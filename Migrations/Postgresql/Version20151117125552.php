<?php

namespace Neos\Flow\Persistence\Doctrine\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use PDO;

/**
 * Set the Workspace "owner" field for all personal workspaces
 */
class Version20151117125552 extends AbstractMigration
{
    /**
     * @param Schema $schema
     * @return void
     */
    public function up(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform));

        $schemaManager = $this->connection->createSchemaManager();
        $hasTables = $schemaManager->tablesExist(['typo3_typo3cr_domain_model_workspace']);
        if ($hasTables) {
            $workspacesQuery
                = $this->connection->executeQuery('SELECT name FROM typo3_typo3cr_domain_model_workspace t0 WHERE t0.name LIKE \'user-%\' AND t0.owner IS NULL');
            while ($workspaceRecord = $workspacesQuery->fetch(PDO::FETCH_ASSOC)) {
                $username = substr($workspaceRecord['name'], 5);
                $accountQuery
                    = $this->connection->executeQuery('SELECT persistence_object_identifier FROM typo3_flow_security_account t0 WHERE t0.accountidentifier = \''
                    . $username . '\' AND t0.authenticationprovidername = \'Typo3BackendProvider\'');
                $accountRecord = $accountQuery->fetch(PDO::FETCH_ASSOC);

                $partyQuery
                    = $this->connection->executeQuery('SELECT party_abstractparty FROM typo3_party_domain_model_abstractparty_accounts_join t0 WHERE t0.flow_security_account = \''
                    . $accountRecord['persistence_object_identifier'] . '\'');
                $partyRecord = $partyQuery->fetch(PDO::FETCH_ASSOC);

                $this->addSql('UPDATE typo3_typo3cr_domain_model_workspace SET owner = \''
                    . $partyRecord['party_abstractparty'] . '\' WHERE name = \'user-' . $username . '\'');
            }
        }
    }

    /**
     * @param Schema $schema
     * @return void
     */
    public function down(Schema $schema): void
    {
        $this->abortIf(!($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform));
        $schemaManager = $this->connection->createSchemaManager();
        $hasTables = $schemaManager->tablesExist(['typo3_typo3cr_domain_model_workspace']);
        if ($hasTables) {
            $this->addSql('UPDATE typo3_typo3cr_domain_model_workspace SET owner = NULL');
        }
    }
}
