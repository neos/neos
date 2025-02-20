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
use Neos\Neos\TypeConverter\NodeAddressToNodeConverter;

/**
 * Aspect to convert a node object to a node address string.
 * This is used in URI building in order to simplify resolving routes with node object route values.
 *
 * Resolving routes
 * ----------------
 *
 * Currently, the router only accepts either primitive php values or flow entities which are encoded via
 * {@see \Neos\Flow\Persistence\AbstractPersistenceManager::convertObjectToIdentityArray()} to array('__identity' => <persistent-object-id>)
 * A simile json node address would work already:
 *
 *    $uriBuilder->uriFor(
 *         'someThing',
 *         ['node' => NodeAddress::fromNode($source)->toJson()]
 *     );
 *
 * A non flow entity object will raise an exception: Tried to convert an object of type "Node" to an identity array, but it is unknown to the Persistence Manager.
 * To continue allowing actual node instances passed we convert the node to array('__contextNodePath' => <json-node-address>).
 *
 *    $uriBuilder->uriFor(
 *         'someThing',
 *         ['node' => $node]
 *    );
 *
 * Its enforced upstream that we need to convert to an array with the json address.
 *
 * Matching routes
 * ---------------
 *
 * When invoking the controller the route values are transformed based on the actions signature.
 * The following action will invoke the {@see NodeAddressToNodeConverter}.
 * It has to work with array or string format - the json node address string and the json node address inside an array.
 *
 *     public function indexAction(Node $node);
 *
 * For more information and a possibly clean solution see https://github.com/neos/neos-development-collection/issues/5069
 *
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class NodeIdentityConverterAspect
{
    /**
     * @Flow\Around("method(Neos\Flow\Persistence\AbstractPersistenceManager->convertObjectToIdentityArray())")
     * @return array<mixed>
     */
    public function convertNodeToContextPathForRouting(JoinPointInterface $joinPoint): array
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
