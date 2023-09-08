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

use Neos\ContentRepository\Core\Factory\ContentRepositoryId;
use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepositoryRegistry\Utility\ContentRepositoryRegistryProvider;
use Neos\Neos\FrontendRouting\NodeAddress;
use Neos\ContentRepository\Core\Projection\ContentGraph\VisibilityConstraints;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Utility\NodeTypeWithFallbackProvider;

#[Flow\Scope('singleton')]
class NodeSiteResolvingService
{
    use ContentRepositoryRegistryProvider;
    use NodeTypeWithFallbackProvider;

    public function findSiteNodeForNodeAddress(
        NodeAddress $nodeAddress,
        ContentRepositoryId $contentRepositoryId
    ): ?Node {
        $contentRepository = $this->contentRepositoryRegistry->get(
            $contentRepositoryId
        );
        $subgraph = $contentRepository->getContentGraph()->getSubgraph(
            $nodeAddress->contentStreamId,
            $nodeAddress->dimensionSpacePoint,
            $nodeAddress->isInLiveWorkspace()
                ? VisibilityConstraints::frontend()
                : VisibilityConstraints::withoutRestrictions()
        );
        $node = $subgraph->findNodeById($nodeAddress->nodeAggregateId);
        if (is_null($node)) {
            return null;
        }
        $previousNode = null;
        do {
            if ($this->getNodeType($node)->isOfType(NodeTypeNameFactory::NAME_SITES)) {
                // the Site node is the one level underneath the "Sites" node.
                return $previousNode;
            }
            $previousNode = $node;
        } while ($node = $subgraph->findParentNode($node->nodeAggregateId));

        // no Site node found at rootline
        return null;
    }
}
