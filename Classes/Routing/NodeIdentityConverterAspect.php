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

namespace Neos\Neos\Routing;

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Aop\JoinPointInterface;

/**
 * Aspect to convert a node object to its context node path. This is used in URI
 * building in order to make linking to nodes a lot easier.
 *
 * On the long term, type converters should be able to convert the reverse direction
 * as well, and then this aspect could be removed.
 *
 * @deprecated todo remove / repair me for Neos 9: https://github.com/neos/neos-development-collection/issues/5069
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class NodeIdentityConverterAspect
{
    /**
     * Convert the object to its context path, if we deal with ContentRepository nodes.
     *
     * @Flow\Around("method(Neos\Flow\Persistence\AbstractPersistenceManager->convertObjectToIdentityArray())")
     * @param JoinPointInterface $joinPoint the joinpoint
     * @return string|array<string,string> the context path to be used for routing
     */
    public function convertNodeToContextPathForRouting(JoinPointInterface $joinPoint): array|string
    {
        $objectArgument = $joinPoint->getMethodArgument('object');
        if ($objectArgument instanceof Node) {
            return ['__contextNodePath' => NodeAddress::fromNode($objectArgument)->toJson()];
        } elseif ($objectArgument instanceof NodeAddress) {
            return ['__contextNodePath' => $objectArgument->toJson()];
        }

        return $joinPoint->getAdviceChain()->proceed($joinPoint);
    }
}
