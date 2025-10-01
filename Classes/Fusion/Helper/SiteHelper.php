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

namespace Neos\Neos\Fusion\Helper;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Neos\Domain\Model\Site;

/**
 * ForwardCompatibility Neos 9.0
 * Eel helper for accessing the Site object
 */
class SiteHelper implements ProtectedContextAwareInterface
{

    public function findBySiteNode(NodeInterface $siteNode): ?Site
    {
        return $siteNode->getContext()->getCurrentSite();
    }

    /**
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
