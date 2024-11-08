<?php

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

declare(strict_types=1);

namespace Neos\Neos\Domain\Service;

use Doctrine\DBAL\Exception as DBALException;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Neos\ContentRepository\Core\ContentRepository;
use Neos\ContentRepository\Core\SharedModel\ContentRepository\ContentRepositoryId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\Export\Factory\EventStoreImportProcessorFactory;
use Neos\ContentRepository\Export\ProcessingContext;
use Neos\ContentRepository\Export\ProcessorInterface;
use Neos\ContentRepository\Export\Processors;
use Neos\ContentRepository\Export\Processors\AssetRepositoryImportProcessor;
use Neos\ContentRepository\Export\Severity;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\ContentRepositoryRegistry\Processors\ProjectionCatchupProcessor;
use Neos\ContentRepositoryRegistry\Service\ProjectionServiceFactory;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\Service as DoctrineService;
use Neos\Flow\Persistence\PersistenceManagerInterface;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\ResourceManagement\ResourceRepository;
use Neos\Media\Domain\Repository\AssetRepository;
use Neos\Neos\Domain\Import\LiveWorkspaceCreationProcessor;
use Neos\Neos\Domain\Import\SiteCreationProcessor;
use Neos\Neos\Domain\Repository\DomainRepository;
use Neos\Neos\Domain\Repository\SiteRepository;

#[Flow\Scope('singleton')]
final readonly class SiteImportService
{
    public function __construct(
        private ContentRepositoryRegistry $contentRepositoryRegistry,
        private DoctrineService $doctrineService,
        private SiteRepository $siteRepository,
        private DomainRepository $domainRepository,
        private AssetRepository $assetRepository,
        private ResourceRepository $resourceRepository,
        private ResourceManager $resourceManager,
        private PersistenceManagerInterface $persistenceManager,
        private WorkspaceService $workspaceService,
    ) {
    }

    /**
     * @param \Closure(string): void $onProcessor Callback that is invoked for each {@see ProcessorInterface} that is processed
     * @param \Closure(Severity, string): void $onMessage Callback that is invoked whenever a {@see ProcessorInterface} dispatches a message
     */
    public function importFromPath(ContentRepositoryId $contentRepositoryId, string $path, \Closure $onProcessor, \Closure $onMessage): void
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(sprintf('Path "%s" is not a directory', $path), 1729593802);
        }
        $contentRepository = $this->contentRepositoryRegistry->get($contentRepositoryId);

        $this->requireDataBaseSchemaToBeSetup();
        $this->requireContentRepositoryToBeSetup($contentRepository);

        $filesystem = new Filesystem(new LocalFilesystemAdapter($path));
        $context = new ProcessingContext($filesystem, $onMessage);

        $processors = Processors::fromArray([
            'Create Live workspace' => new LiveWorkspaceCreationProcessor($contentRepository, $this->workspaceService),
            'Create Neos sites' => new SiteCreationProcessor($this->siteRepository, $this->domainRepository, $this->persistenceManager),
            'Import events' => $this->contentRepositoryRegistry->buildService($contentRepositoryId, new EventStoreImportProcessorFactory(WorkspaceName::forLive(), keepEventIds: true)),
            'Import assets' => new AssetRepositoryImportProcessor($this->assetRepository, $this->resourceRepository, $this->resourceManager, $this->persistenceManager),
            'Catchup all projections' => new ProjectionCatchupProcessor($this->contentRepositoryRegistry->buildService($contentRepositoryId, new ProjectionServiceFactory())),
        ]);

        foreach ($processors as $processorLabel => $processor) {
            ($onProcessor)($processorLabel);
            $processor->run($context);
        }
    }

    private function requireContentRepositoryToBeSetup(ContentRepository $contentRepository): void
    {
        $status = $contentRepository->status();
        if (!$status->isOk()) {
            throw new \RuntimeException(sprintf('Content repository %s is not setup correctly, please run `./flow cr:setup`', $contentRepository->id->value));
        }
    }

    private function requireDataBaseSchemaToBeSetup(): void
    {
        try {
            [
                'new' => $_newMigrationCount,
                'executed' => $executedMigrationCount,
                'available' => $availableMigrationCount
            ] = $this->doctrineService->getMigrationStatus();
        } catch (DBALException | \PDOException) {
            throw new \RuntimeException('Not database connected. Please check your database connection settings or run `./flow setup` for further information.', 1684075689386);
        }

        if ($executedMigrationCount === 0 && $availableMigrationCount > 0) {
            throw new \RuntimeException('No doctrine migrations have been executed. Please run `./flow doctrine:migrate`');
        }
    }
}
