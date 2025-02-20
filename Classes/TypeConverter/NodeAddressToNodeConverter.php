<?php

declare(strict_types=1);

namespace Neos\Neos\TypeConverter;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Core\Projection\ContentGraph\Node;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAddress;
use Neos\ContentRepositoryRegistry\ContentRepositoryRegistry;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Property\PropertyMappingConfigurationInterface;
use Neos\Flow\Property\TypeConverter\AbstractTypeConverter;

/**
 * @Flow\Scope("singleton")
 */
class NodeAddressToNodeConverter extends AbstractTypeConverter
{
    /**
     * @var array<int,string>
     */
    protected $sourceTypes = ['string', 'array'];

    /**
     * @var string
     */
    protected $targetType = Node::class;

    /**
     * @var integer
     */
    protected $priority = 2;

    #[Flow\Inject]
    protected ContentRepositoryRegistry $contentRepositoryRegistry;

    public function canConvertFrom($source, $targetType)
    {
        return is_string($source) || (is_array($source) && array_key_exists('__contextNodePath', $source));
    }

    /**
     * @param string $source
     * @param string $targetType
     * @param array<string,string> $subProperties
     * @return ?Node
     */
    public function convertFrom(
        $source,
        $targetType = null,
        array $subProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null
    ) {
        if (is_array($source)) {
            $source = $source['__contextNodePath'];
        }
        $nodeAddress = NodeAddress::fromJsonString($source);
        $contentRepository = $this->contentRepositoryRegistry->get($nodeAddress->contentRepositoryId);
        $subgraph = $contentRepository->getContentSubgraph($nodeAddress->workspaceName, $nodeAddress->dimensionSpacePoint);

        return $subgraph->findNodeById($nodeAddress->aggregateId);
    }
}
