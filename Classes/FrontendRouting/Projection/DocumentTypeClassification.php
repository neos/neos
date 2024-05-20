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

namespace Neos\Neos\FrontendRouting\Projection;

use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\Neos\Domain\Service\NodeTypeNameFactory;

enum DocumentTypeClassification: string
{
    /**
     * Satisfied if a node type is an actual document, meaning explicitly no shortcut or site
     */
    case CLASSIFICATION_DOCUMENT = 'document';

    /**
     * Satisfied if a node type is a shortcut
     */
    case CLASSIFICATION_SHORTCUT = 'shortcut';

    /**
     * Satisfied if a node type is a site
     */
    case CLASSIFICATION_SITE = 'site';

    /**
     * Satisfied if a node type is neither of the above
     */
    case CLASSIFICATION_NONE = 'none';

    /**
     * Satisfied if a node type does no longer exist and we can't be certain
     */
    case CLASSIFICATION_UNKNOWN = 'unknown';

    public static function forNodeType(NodeTypeName $nodeTypeName, NodeTypeManager $nodeTypeManager): self
    {
        $nodeType = $nodeTypeManager->getNodeType($nodeTypeName);
        if ($nodeType === null) {
            return self::CLASSIFICATION_UNKNOWN;
        }

        if ($nodeType->isOfType(NodeTypeNameFactory::forSite())) {
            return self::CLASSIFICATION_SITE;
        } elseif ($nodeType->isOfType(NodeTypeNameFactory::forShortcut())) {
            return self::CLASSIFICATION_SHORTCUT;
        } elseif ($nodeType->isOfType(NodeTypeNameFactory::forDocument())) {
            return self::CLASSIFICATION_DOCUMENT;
        }

        return self::CLASSIFICATION_NONE;
    }
}
