<?php

namespace Neos\Neos\Domain\Service\NodeDuplication;

use Flowpack\NodeTemplates\Domain\NodeCreation\NodeConstraintException;
use Neos\ContentRepository\Core\DimensionSpace\OriginDimensionSpacePoint;
use Neos\ContentRepository\Core\Feature\NodeCreation\Dto\NodeAggregateIdsByNodePaths;
use Neos\ContentRepository\Core\Feature\NodeDuplication\Dto\NodeAggregateIdMapping;
use Neos\ContentRepository\Core\NodeType\NodeType;
use Neos\ContentRepository\Core\NodeType\NodeTypeManager;
use Neos\ContentRepository\Core\NodeType\NodeTypeName;
use Neos\ContentRepository\Core\Projection\ContentGraph\NodePath;
use Neos\ContentRepository\Core\Projection\ContentGraph\Subtree;
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
final readonly class TransientNodeCopy
{
    private function __construct(
        public NodeAggregateId $aggregateId,
        public WorkspaceName $workspaceName,
        public OriginDimensionSpacePoint $originDimensionSpacePoint,
        private NodeType $nodeType,
        /**
         * @var NodeAggregateIdMapping An assignment of "old" to "new" NodeAggregateIds
         */
        private NodeAggregateIdMapping $nodeAggregateIdMapping,
        /**
         * @var NodeAggregateIdsByNodePaths Deterministic NodeAggregate ids for the creation of tethered nodes (empty if there are no tethered nodes)
         */
        public NodeAggregateIdsByNodePaths $tetheredNodeAggregateIds,
        private ?NodeName $tetheredNodeName,
        private ?NodeType $tetheredParentNodeType,
        private NodeTypeManager $nodeTypeManager
    ) {
        if ($this->tetheredNodeName !== null) {
            assert($this->tetheredParentNodeType !== null);
        }
    }

    public static function forRegular(
        Subtree $subtree,
        WorkspaceName $targetWorkspaceName,
        OriginDimensionSpacePoint $targetOriginDimensionSpacePoint,
        NodeAggregateIdMapping $nodeAggregateIdMapping,
        NodeTypeManager $nodeTypeManager
    ): self {
        $nodeType = $nodeTypeManager->getNodeType($subtree->node->nodeTypeName);
        return new self(
            $nodeAggregateIdMapping->getNewNodeAggregateId($subtree->node->aggregateId) ?? NodeAggregateId::create(),
            $targetWorkspaceName,
            $targetOriginDimensionSpacePoint,
            $nodeType,
            $nodeAggregateIdMapping,
            self::getTetheredDescendantNodeAggregateIds($subtree->children, $nodeAggregateIdMapping, NodePath::forRoot(), NodeAggregateIdsByNodePaths::createEmpty()),
            null,
            null,
            $nodeTypeManager
        );
    }

    public function forTetheredChildNode(Subtree $subtree): self
    {
        $nodeName = $subtree->node->name;
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

        // keep tethered node aggregate ids from parent
        $descendantTetheredNodeAggregateIds = NodeAggregateIdsByNodePaths::createEmpty();
        foreach ($this->tetheredNodeAggregateIds->getNodeAggregateIds() as $stringNodePath => $descendantNodeAggregateId) {
            $nodePath = NodePath::fromString($stringNodePath);
            $pathParts = $nodePath->getParts();
            $firstPart = array_shift($pathParts);
            if ($firstPart?->equals($nodeName) && count($pathParts)) {
                $descendantTetheredNodeAggregateIds = $descendantTetheredNodeAggregateIds->add(
                    NodePath::fromNodeNames(...$pathParts),
                    $descendantNodeAggregateId
                );
            }
        }


        return new self(
            $nodeAggregateId,
            $this->workspaceName,
            $this->originDimensionSpacePoint,
            $childNodeType,
            $this->nodeAggregateIdMapping,
            self::getTetheredDescendantNodeAggregateIds($subtree->children, $this->nodeAggregateIdMapping, NodePath::forRoot(), $descendantTetheredNodeAggregateIds),
            $nodeName,
            $this->nodeType,
            $this->nodeTypeManager
        );
    }

    public function forRegularChildNode(Subtree $subtree): self
    {
        $nodeType = $this->nodeTypeManager->getNodeType($subtree->node->nodeTypeName);
        return new self(
            $this->nodeAggregateIdMapping->getNewNodeAggregateId($subtree->node->aggregateId) ?? NodeAggregateId::create(),
            $this->workspaceName,
            $this->originDimensionSpacePoint,
            $nodeType,
            $this->nodeAggregateIdMapping,
            self::getTetheredDescendantNodeAggregateIds($subtree->children, $this->nodeAggregateIdMapping, NodePath::forRoot(), NodeAggregateIdsByNodePaths::createEmpty()),
            null,
            null,
            $this->nodeTypeManager
        );
    }

    /**
     * @param array<Subtree> $subtreeChildren
     */
    private static function getTetheredDescendantNodeAggregateIds(array $subtreeChildren, NodeAggregateIdMapping $nodeAggregateIdMapping, NodePath $nodePath, NodeAggregateIdsByNodePaths $tetheredNodeAggregateIds): NodeAggregateIdsByNodePaths
    {
        foreach ($subtreeChildren as $childSubtree) {
            if (!$childSubtree->node->classification->isTethered()) {
                continue;
            }

            $deterministicCopyAggregateId = $nodeAggregateIdMapping->getNewNodeAggregateId($childSubtree->node->aggregateId) ?? NodeAggregateId::create();

            $childNodePath = $nodePath->appendPathSegment($childSubtree->node->name);

            $tetheredNodeAggregateIds = $tetheredNodeAggregateIds->add(
                $childNodePath,
                $deterministicCopyAggregateId
            );

            $tetheredNodeAggregateIds = self::getTetheredDescendantNodeAggregateIds($childSubtree->children, $nodeAggregateIdMapping, $childNodePath, $tetheredNodeAggregateIds);
        }

        return $tetheredNodeAggregateIds;
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
