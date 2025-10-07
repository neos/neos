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

namespace Neos\Neos\Domain\Model;

use Neos\Neos\Domain\Service\ContentContext;

/**
 * Describes the mode in which the Neos interface is rendering currently,
 * mainly distinguishing between edit and preview modes currently.
 */
final readonly class Neos9NodeBasedRenderingModeStub
{
    public const FRONTEND = 'frontend';

    /**
     * @param array<string,mixed> $options
     */
    private function __construct(
        public string $name,
        public bool $isEdit,
        public bool $isPreview,
        public string $title,
        public string $fusionPath,
        public array $options
    ) {
    }

    public static function createFromLegacyNodeContentContext(ContentContext $contentContext): Neos9NodeBasedRenderingModeStub
    {
        if (!$contentContext->isInBackend()) {
            return self::createFrontend();
        }
        return self::createFromLegacy($contentContext->getCurrentRenderingMode());
    }

    public static function createFromLegacy(UserInterfaceMode $userInterfaceMode): Neos9NodeBasedRenderingModeStub
    {
        return new self(
            $userInterfaceMode->getName(),
            $userInterfaceMode->isEdit(),
            $userInterfaceMode->isPreview(),
            $userInterfaceMode->getTitle(),
            $userInterfaceMode->getFusionPath(),
            $userInterfaceMode->getOptions() ?? []
        );
    }

    /**
     * Creates the system integrated rendering mode 'frontend' (in Neos 8 referred to as 'live')
     */
    public static function createFrontend(): Neos9NodeBasedRenderingModeStub
    {
        return new self(
            Neos9NodeBasedRenderingModeStub::FRONTEND,
            false,
            false,
            'Frontend',
            '',
            []
        );
    }
}
