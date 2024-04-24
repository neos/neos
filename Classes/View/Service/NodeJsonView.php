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

namespace Neos\Neos\View\Service;

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Mvc\View\JsonView;
use Psr\Http\Message\ResponseInterface;

/**
 * A view specialised on a JSON representation of Nodes.
 *
 * This view is used by the service controllers in Neos\Neos\Controller\Service\
 *
 * @deprecated with Neos 9, the JsonView should not be used
 * @internal only to be used internally
 * @Flow\Scope("prototype")
 */
class NodeJsonView extends JsonView
{
    /**
     * Configures rendering according to the set variable(s) and calls
     * render on the parent.
     */
    public function render(): ResponseInterface
    {
        if (isset($this->variables['nodes'])) {
            $this->setConfiguration(
                [
                    'nodes' => [
                        '_descendAll' => [
                            '_only' => ['name', 'path', 'identifier', 'properties', 'nodeType']
                        ]
                    ]
                ]
            );
            $this->setVariablesToRender(['nodes']);
        } else {
            $this->setConfiguration(
                [
                    'node' => [
                        '_only' => ['name', 'path', 'identifier', 'properties', 'nodeType']
                    ]
                ]
            );
            $this->setVariablesToRender(['node']);
        }

        return parent::render();
    }
}
