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
use Neos\Neos\Domain\Repository\SiteRepository;
use Neos\Neos\Domain\Service\SiteService;
use Neos\Flow\Annotations as Flow;

/**
 * ForwardCompatibility Neos 9.0
 * Eel helper for accessing the Site object
 */
class SiteHelper implements ProtectedContextAwareInterface
{
    #[Flow\Inject]
    protected SiteRepository $siteRepository;

    public function findBySiteNode(NodeInterface $siteNode): ?Site
    {
        if ($siteNode->getParentPath() !== SiteService::SITES_ROOT_PATH) {
            return null; // not a side node
        }
        return $this->siteRepository->findOneByNodeName($siteNode->getName());
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
