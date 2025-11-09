<?php

declare(strict_types=1);

namespace Neos\Neos\Tests\Unit\CodeMigrations\Version20251109115127;

use Neos\Flow\Core\Migrations\Manager;
use Neos\Flow\Core\Migrations\Version20251109115127;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class Version20251109115127Test extends TestCase
{
    public static function fixtures(): iterable
    {
        $filePaths = new \RegexIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(__DIR__ . '/Fixture'),
            ),
            '/\.yaml.inc$/',
            \RecursiveRegexIterator::GET_MATCH,
        );

        foreach ($filePaths as $filePath => $_) {
            $contents = file_get_contents($filePath);
            $parts = explode("\n-----\n", $contents);
            if (count($parts) !== 2) {
                throw new \RuntimeException(sprintf('Expect exact two segments split by ----- in file %s', $filePath), 1759646552);
            }
            yield $filePath => [
                rtrim($parts[0]),
                rtrim($parts[1])
            ];
        }
    }

    /**
     * @dataProvider fixtures
     * @test
     */
    public function executeMigration(string $yamlInputFile, string $expectedYamlOutputFile): void
    {
        vfsStream::setup('yaml', null, [
            "Target.Package" => [
                'Configuration' => [
                    'Settings.SomeFile.yaml' => $yamlInputFile
                ],
            ]
        ]);

        $fakeManager = $this->getMockBuilder(Manager::class)->disableOriginalConstructor()->disableAutoReturnValueGeneration()->getMock();

        if (!class_exists(Version20251109115127::class)) {
            // migrations are not PSR auto-loaded
            require_once __DIR__ . '/../../../../Migrations/Code/Version20251109115127.php';
        }

        $migration = new Version20251109115127(
            $fakeManager,
            'Neos.Neos'
        );

        $targetPackageData = [
            'path' => 'vfs://yaml/Target.Package'
        ];

        $migration->prepare($targetPackageData);
        $migration->up();
        $migration->execute();

        self::assertEquals(
            $expectedYamlOutputFile,
            rtrim(file_get_contents('vfs://yaml/Target.Package/Configuration/Settings.SomeFile.yaml'))
        );

        self::assertEmpty($migration->getWarnings());
    }

    public function reExecuteMigrationEmitsNoChanges(string $_, string $migratedYamlFile): void
    {
        vfsStream::setup('yaml', null, [
            "Target.Package" => [
                'Configuration' => [
                    'Settings.SomeFile.yaml' => $migratedYamlFile
                ],
            ]
        ]);

        $fakeManager = $this->getMockBuilder(Manager::class)->disableOriginalConstructor()->disableAutoReturnValueGeneration()->getMock();

        if (!class_exists(Version20251109115127::class)) {
            // migrations are not PSR auto-loaded
            require_once __DIR__ . '/../../../../Migrations/Code/Version20251109115127.php';
        }

        $migration = new Version20251109115127(
            $fakeManager,
            'Neos.Neos'
        );

        $targetPackageData = [
            'path' => 'vfs://yaml/Target.Package'
        ];

        $migration->prepare($targetPackageData);
        $migration->up();

        $migration->execute();

        self::assertEquals(
            $migratedYamlFile,
            file_get_contents('vfs://fusion/Target.Package/Configuration/Settings.SomeFile.yaml')
        );

        self::assertEmpty($migration->getWarnings());
    }
}
