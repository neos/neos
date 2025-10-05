<?php
namespace Neos\Flow\Core\Migrations;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Fusion\Migrations\FusionMigrationTrait;

/**
 * TODO Add description as this is part of the documentation
 */
class Version20251005080230 extends AbstractMigration
{
    use FusionMigrationTrait;

    public function getIdentifier(): string
    {
        return 'Neos.Neos-20251005080230';
    }

    public function up(): void
    {
        $this->replaceEelExpression('/node.context.inBackend/', 'renderingMode.isEdit');
    }
}
