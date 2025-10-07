<?php

declare(strict_types=1);

namespace Neos\Neos\Command;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Core\Migrations\Git;
use Neos\Flow\Core\Migrations\Version20251005080230;
use Neos\Flow\Package\PackageManager;
use Neos\Utility\Files;

/**
 * @Flow\Scope("singleton")
 */
class Neos9PreparationCommandController extends CommandController
{
    /**
     * @Flow\Inject
     */
    protected PackageManager $packageManager;

    /**
     * Pre-adjust EEL in Fusion code to the new Neos 9 API
     * to hopefully ease the migration when doing the actual Neos 9.0 update.
     * this is possible because Neos 8.4 provides the new Runtime API already
     *
     * The context variables ${node}, documentNode and site continue to exist in Fusion but there are changes to their API in Fusion.
     *
     * While most of the FlowQueries work as before, there are some adjustments that come with the new concepts that are introduced in Neos 9.
     *
     * The most important changes are:
     *
     * - Accessing properties of the node context via node.context.is no longer supported.
     *   And modifying the node context via flowQuery q(node).context() is only partially supported.
     *   The rendering mode (node.context.inBackend) is now moved to a separate variable that is independent of the node context.
     * - Internal properties like _hidden and _name are no longer in use.
     * - Cache Entry Identifiers are now a dedicated object and not any value.
     *
     * There are some adjustments with a caveat as they don't reflect the 8.3 behaviour 100%.
     *
     * A few examples are:
     *
     * - `node.nodeType` always returned a NodeType and when removed the `Neos.Neos:FallbackNode`. In Neos 9.0 there exists no magic for the `Neos.Neos:FallbackNode` and thus the helper `Neos.Node.nodeType(node)` returns "NULL".
     * - `node.context.currentRenderingMode` always returns the rendering mode based on the logged-in user - so when viewing the page logged in in the frontend the mode is still 'inPlaces' as in the backend -> the new `renderingMode` reports "frontend" as expected for this case
     * - the "live" rendering mode was renamed to "frontend" so the unlikely case of `node.context.currentRenderingMode == "live"` fails when migrated to `renderingMode.name`
     * - `node.context.currentSite` is rewritten to `Neos.Site.findBySiteNode(site)` which makes the assumption of "site" being present and that "currentSite" is actually the current and was not tampered with
     * - unlike `node.identifier` the `node.aggregateId` is now a value object in fusion, it is string-able and can be output directly but no direct strict comparison must be done `node.aggregateId == "some-id"` will not work. It has to be cast to string `String.toString(node.aggregateId)`.
     *
     * @param string $packageKey The key of the package to migrate
     * @param boolean $force By default packages that are not under version control or contain local changes are skipped. With this flag set changes are applied anyways (changes are not committed if there are local changes though)
     */
    public function preAdjustFusionCommand(string $packageKey, bool $force = false): void
    {
        if (Git::isGitAvailable() === false) {
            $this->outputLine('No executable git binary found, exiting.');
            $this->quit(255);
        }

        $packagePath = $this->packageManager->getPackage($packageKey)->getPackagePath();

        if (!$force) {
            $isWorkingCopy = Git::isWorkingCopy($packagePath);
            $hasLocalChanges = Git::isWorkingCopyDirty($packagePath);
            if (!$isWorkingCopy) {
                $this->outputLine('Not a Git working copy, use --force to apply changes anyways');
                $this->quit(1);
            }
            if ($hasLocalChanges) {
                $this->outputLine('Working copy contains local changes, use --force to apply changes anyways');
                $this->quit(1);
            }
        }

        $migrationFile = Files::concatenatePaths([
            $this->packageManager->getPackage('Neos.Neos')->getPackagePath(),
            'Migrations',
            'Version20251005080230.php'
        ]);

        require_once $migrationFile;

        $migration = new Version20251005080230();

        $targetPackageData = [
            'path' => $packagePath
        ];

        $migration->prepare($targetPackageData);

        $migration->up();
        // when preparing in Neos 8.4 we don't want to emit comments as users wouldn't have the workarounds of Neos 9 available.
        // also adding the comments would make it impractical to rerun the migration for the code once Neos 9 is updated to as all the comments are duplicated.
        $migration->disableAddingTodoComments();

        $migration->execute();

        $this->outputLine('All changes were applied to your working directory. Please go ahead and review and commit them.');
        $this->outputLine('When updating to Neos 9 this command will be removed but `flow flow:core:migrate` will help you with a final polish.');
    }
}
