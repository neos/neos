<?php

declare(strict_types=1);

namespace Neos\Neos\Tests\Unit\CodeMigrations\Version20251005080230;

use Neos\Flow\Core\Migrations\Manager;
use Neos\Flow\Core\Migrations\Version20251005080230;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class Version20251005080230Test extends TestCase
{
    public static function fixtures(): iterable
    {
        $filePaths = new \RegexIterator(
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(__DIR__ . '/Fixture'),
            ),
            '/\.fusion.inc$/',
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
    public function executeMigration(string $fusionInputFile, string $expectedFusionOutputFile): void
    {
        vfsStream::setup('fusion', null, [
            "Target.Package" => [
                'Resources' => [
                    'SomeFile.fusion' => $fusionInputFile
                ],
            ]
        ]);

        $fakeManager = $this->getMockBuilder(Manager::class)->disableOriginalConstructor()->disableAutoReturnValueGeneration()->getMock();

        if (!class_exists(Version20251005080230::class)) {
            // migrations are not PSR auto-loaded
            require_once __DIR__ . '/../../../../Migrations/Version20251005080230.php';
        }

        $migration = new Version20251005080230(
            $fakeManager,
            'Neos.Neos'
        );

        $targetPackageData = [
            'path' => 'vfs://fusion/Target.Package'
        ];

        $migration->prepare($targetPackageData);
        $migration->up();
        $migration->execute();

        self::assertEquals(
            $expectedFusionOutputFile,
            file_get_contents('vfs://fusion/Target.Package/Resources/SomeFile.fusion')
        );
    }
}
