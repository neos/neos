<?php

namespace Neos\Neos\Domain\Service\NodeDuplication;

use Flowpack\NodeTemplates\Domain\NodeCreation\NodeConstraintException;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Dto\NodeAggregateIdsByNodePaths;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodePath;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Node\NodeName;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\Flow\Annotations as Flow;

/**
 * Model about a non materialized node.
 *
 * The "to be created" node is not yet available - (and may never be created in case of an error).
 *
 * The transient node makes it possible, to still be able to enforce constraints {@see self::requireNodeTypeConstraintsImposedByGrandparentToBeMet()}
 * and get information {@see self::$aggregateId} about a node.
 *
 * For example the transient node can be passed as fictional $parentNode.
 * To create child transient nodes of the $parentNode use {@see self::forRegularChildNode()} and {@see self::forTetheredChildNode()}
 *
 * An initial transient node can be created with {@see self::forRegular}
 *
 * @Flow\Proxy(false)
 */
final readonly class TransientNode
{
    private function __construct(
        public NodeAggregateId $aggregateId,
        public WorkspaceName $workspaceName,
        public OriginDimensionSpacePoint $originDimensionSpacePoint,
        private NodeType $nodeType,
        private NodeAggregateIdsByNodePaths $tetheredNodeAggregateIds,
        private ?NodeName $tetheredNodeName,
        private ?NodeType $tetheredParentNodeType,
        private NodeTypeManager $nodeTypeManager
    ) {
        if ($this->tetheredNodeName !== null) {
            assert($this->tetheredParentNodeType !== null);
        }
    }

    public static function forRegular(
        NodeAggregateId $nodeAggregateId,
        WorkspaceName $workspaceName,
        OriginDimensionSpacePoint $originDimensionSpacePoint,
        NodeTypeName $nodeTypeName,
        NodeAggregateIdsByNodePaths $tetheredNodeAggregateIds,
        NodeTypeManager $nodeTypeManager
    ): self {
        $nodeType = $nodeTypeManager->getNodeType($nodeTypeName);
        return new self(
            $nodeAggregateId,
            $workspaceName,
            $originDimensionSpacePoint,
            $nodeType,
            $tetheredNodeAggregateIds,
            null,
            null,
            $nodeTypeManager
        );
    }

    public function forTetheredChildNode(NodeName $nodeName): self
    {
        $nodeAggregateId = $this->tetheredNodeAggregateIds->getNodeAggregateId(NodePath::fromNodeNames($nodeName));

        if (!$nodeAggregateId) {
            throw new \InvalidArgumentException(sprintf('Name "%s" doesnt seem to be a point to a tethered node of "%s", could not determine deterministic node aggregate id.', $nodeName->value, $this->aggregateId->value));
        }

        $tetheredNodeTypeDefinition = $this->nodeType->tetheredNodeTypeDefinitions->get($nodeName);

        if (!$tetheredNodeTypeDefinition) {
            throw new \InvalidArgumentException(sprintf('Name "%s" doesnt match any tethered node type definition in the schema. Parent node "%s"', $nodeName->value, $this->aggregateId->value));
        }

        $childNodeType = $this->nodeTypeManager->getNodeType($tetheredNodeTypeDefinition->nodeTypeName);
        if (!$childNodeType) {
            throw new \InvalidArgumentException(sprintf('NodeType "%s" for tethered node "%s" does not exist.', $tetheredNodeTypeDefinition->nodeTypeName->value, $nodeName->value), 1718950833);
        }

        return new self(
            $nodeAggregateId,
            $this->workspaceName,
            $this->originDimensionSpacePoint,
            $childNodeType,
            NodeAggregateIdsByNodePaths::createEmpty(),
            $nodeName,
            $this->nodeType,
            $this->nodeTypeManager
        );
    }

    public function forRegularChildNode(NodeAggregateId $nodeAggregateId, NodeTypeName $nodeTypeName): self
    {
        $nodeType = $this->nodeTypeManager->getNodeType($nodeTypeName);
        $tetheredNodeAggregateIds = NodeAggregateIdsByNodePaths::createForNodeType($nodeTypeName, $this->nodeTypeManager);
        return new self(
            $nodeAggregateId,
            $this->workspaceName,
            $this->originDimensionSpacePoint,
            $nodeType,
            $tetheredNodeAggregateIds,
            null,
            null,
            $this->nodeTypeManager
        );
    }

    public function withTetheredNodeAggregateIds(NodeAggregateIdsByNodePaths $nodeAggregateIdsByNodePaths): self
    {
        return new self(
            $this->aggregateId,
            $this->workspaceName,
            $this->originDimensionSpacePoint,
            $this->nodeType,
            $nodeAggregateIdsByNodePaths,
            $this->tetheredNodeName,
            $this->tetheredParentNodeType,
            $this->nodeTypeManager
        );
    }

    /**
     * @throws NodeConstraintException
     */
    public function requireConstraintsImposedByAncestorsToBeMet(NodeType $childNodeType): void
    {
        if ($this->isTethered()) {
            $this->requireNodeTypeConstraintsImposedByGrandparentToBeMet($this->tetheredParentNodeType->name, $this->tetheredNodeName, $childNodeType->name);
        } else {
            self::requireNodeTypeConstraintsImposedByParentToBeMet($this->nodeType, $childNodeType);
        }
    }

    private static function requireNodeTypeConstraintsImposedByParentToBeMet(NodeType $parentNodeType, NodeType $nodeType): void
    {
        if (!$parentNodeType->allowsChildNodeType($nodeType)) {
            throw new NodeConstraintException(
                sprintf(
                    'Node type "%s" is not allowed for child nodes of type %s',
                    $nodeType->name->value,
                    $parentNodeType->name->value
                ),
                1686417627173
            );
        }
    }

    private function requireNodeTypeConstraintsImposedByGrandparentToBeMet(NodeTypeName $parentNodeTypeName, NodeName $tetheredNodeName, NodeTypeName $nodeTypeNameToCheck): void
    {
        if (!$this->nodeTypeManager->isNodeTypeAllowedAsChildToTetheredNode($parentNodeTypeName, $tetheredNodeName, $nodeTypeNameToCheck)) {
            throw new NodeConstraintException(
                sprintf(
                    'Node type "%s" is not allowed below tethered child nodes "%s" of nodes of type "%s"',
                    $nodeTypeNameToCheck->value,
                    $tetheredNodeName->value,
                    $parentNodeTypeName->value
                ),
                1687541480146
            );
        }
    }

    /**
     * @phpstan-assert-if-true !null $this->tetheredNodeName
     * @phpstan-assert-if-true !null $this->tetheredParentNodeType
     */
    private function isTethered(): bool
    {
        return $this->tetheredNodeName !== null;
    }
}
