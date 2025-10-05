<?php
namespace Neos\Flow\Core\Migrations;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Fusion\Migrations\FusionMigrationTrait;

/**
 * TODO Add description as this is part of the documentation
 */
class Version20251005080230 extends AbstractMigration
{
    use FusionMigrationTrait;

    public function getIdentifier(): string
    {
        return 'Neos.Neos-20251005080230';
    }

    final public function fusionFlowQueryNodePropertyToWarningComment(string $property, string $warningMessage): void
    {
        $this->addCommentsIfRegexMatches(
            "/\.property\(('|\")$property('|\")\)/",
             '// TODO 9.0 migration: ' . $warningMessage
        );
    }

    final public function fusionNodePropertyPathToWarningComment(string $propertyPath, string $warningMessage): void
    {
        // escape the fusion path separator "."
        $propertyPath = str_replace('.', '\.', $propertyPath);

        $this->addCommentsIfRegexMatches(
            "/(node|site|documentNode)\.$propertyPath/",
             '// TODO 9.0 migration: ' . $warningMessage
        );
    }

    public function up(): void
    {
        // todo $rectorConfig->ruleWithConfiguration(FusionReplacePrototypeNameRector::class, [
        //     new FusionPrototypeNameReplacement('Neos.Fusion:Array', 'Neos.Fusion:Join'),
        //     new FusionPrototypeNameReplacement('Neos.Fusion:RawArray', 'Neos.Fusion:DataStructure'),
        //     new FusionPrototypeNameReplacement('Neos.Fusion:Collection', 'Neos.Fusion:Loop',
        //         'Migration of Neos.Fusion:Collection to Neos.Fusion:Loop needs manual action. The key `collection` has to be renamed to `items` which cannot be done automatically'
        //     ),
        //     new FusionPrototypeNameReplacement('Neos.Fusion:RawCollection', 'Neos.Fusion:Map',
        //         'Migration of Neos.Fusion:RawCollection to Neos.Fusion:Map needs manual action. The key `collection` has to be renamed to `items` which cannot be done automatically'
        //     ),
        //     new FusionPrototypeNameReplacement('Neos.Neos:PrimaryContent', 'Neos.Neos:ContentCollection', '"Neos.Neos:PrimaryContent" has been removed without a complete replacement. We replaced all usages with "Neos.Neos:ContentCollection" but not the prototype definition. Please check the replacements and if you have overridden the "Neos.Neos:PrimaryContent" prototype and rewrite it for your needs.', true),
        // ]);

        /**
         * Neos\ContentRepository\Domain\Model\NodeInterface
         */
        // getName
        $this->fusionFlowQueryNodePropertyToWarningComment('_name', 'Line %LINE: !! You very likely need to rewrite "q(VARIABLE).property("_name")" to "VARIABLE.nodeName". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // getLabel
        // Rewrite "node.label" and "q(node).property('_label')" to "Neos.Node.label(node)"
        $this->replaceEelExpression('/(node|documentNode|site)\.label/', 'Neos.Node.label($1)');
        $this->addCommentsIfRegexMatches('/\.label\b(?!\()/', '// TODO 9.0 migration: Line %LINE: You very likely need to rewrite "VARIABLE.label" to "Neos.Node.label(VARIABLE)". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        $this->replaceEelExpression('/q\(([^)]+)\)\.property\\([\'"]_label[\'"]\\)/', 'Neos.Node.label($1)');
        // getProperties -> PropertyCollectionInterface
        // getPropertyNames TODO
        // getContentObject -> DEPRECATED / NON-FUNCTIONAL TODO
        // getNodeType: NodeType
        // Rewrite "node.nodeType" and "q(node).property('_nodeType')" to "Neos.Node.nodeType(node)"
        // Fusion: node.nodeType -> Neos.Node.nodeType(node)
        // Fusion: node.nodeType.name -> node.nodeTypeName
        $this->replaceEelExpression('/(node|documentNode|site)\.nodeType\.name/', '$1.nodeTypeName');
        $this->replaceEelExpression('/(node|documentNode|site)\.nodeType\b/', 'Neos.Node.nodeType($1)');
        $this->addCommentsIfRegexMatches('/\.nodeType\b(?!\()/', '// TODO 9.0 migration: Line %LINE: You very likely need to rewrite "VARIABLE.nodeType" to "Neos.Node.nodeType(VARIABLE)". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        $this->replaceEelExpression('/q\(([^)]+)\)\.property\\([\'"]_nodeType\.name[\'"]\\)/', '$1.nodeTypeName');
        $this->replaceEelExpression('/q\(([^)]+)\)\.property\\([\'"]_nodeType(\.[^\'"]*)?[\'"]\\)/', 'Neos.Node.nodeType($1)$2');
        // isHidden
        // Rewrite node.hidden and q(node).property("_hidden") to Neos.Node.isDisabled(node)
        $this->replaceEelExpression('/(node|documentNode|site)\.hidden\b(?!\.|\()/', 'Neos.Node.isDisabled($1)');
        $this->addCommentsIfRegexMatches('/\.hidden\b(?!\.|\()/', '// TODO 9.0 migration: Line %LINE: You may need to rewrite "VARIABLE.hidden" to Neos.Node.isDisabled(VARIABLE). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        $this->replaceEelExpression('/q\(([^)]+)\)\.property\([\'"]_hidden[\'"]\)/', 'Neos.Node.isDisabled($1)');
        $this->fusionFlowQueryNodePropertyToWarningComment('_hidden', 'Line %LINE: You may need to rewrite "q(VARIABLE).property(\'_hidden\')" to Neos.Node.isDisabled(VARIABLE). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // getHiddenBeforeDateTime
        // Rewrite node.hiddenBeforeDateTime to q(node).property("enableAfterDateTime")'
        $this->replaceEelExpression('/(node|documentNode)\.hiddenBeforeDateTime/', 'q($1).property("enableAfterDateTime")');
        $this->replaceEelExpression('/.property\(["\']_hiddenBeforeDateTime["\']\)/', '.property("enableAfterDateTime")');
        $this->addCommentsIfRegexMatches('/\.hiddenBeforeDateTime/', '// TODO 9.0 migration: Line %LINE: You may need to rewrite "VARIABLE.hiddenBeforeDateTime" to q(VARIABLE).property("enableAfterDateTime"). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // getHiddenAfterDateTime
        // Rewrite node.hiddenAfterDateTime to q(node).property("disableAfterDateTime")
        $this->replaceEelExpression('/(node|documentNode)\.hiddenAfterDateTime/', 'q($1).property("disableAfterDateTime")');
        $this->replaceEelExpression('/.property\(["\']_hiddenAfterDateTime["\']\)/', '.property("disableAfterDateTime")');
        $this->addCommentsIfRegexMatches('/\.hiddenAfterDateTime/', '// TODO 9.0 migration: Line %LINE: You may need to rewrite "VARIABLE.hiddenAfterDateTime" to q(VARIABLE).property("disableAfterDateTime"). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // isHiddenInIndex
        // Fusion: .hiddenInIndex -> node.properties._hiddenInIndex
        // Rewrite node.hiddenInIndex and q(node).property("_hiddenInIndex") to node.property('hiddenInMenu')
        $this->replaceEelExpression('/(node|documentNode|site)\.hiddenInIndex\b(?!\.|\()/', '$1.property(\'hiddenInMenu\')');
        $this->addCommentsIfRegexMatches('/\.hiddenInIndex\b(?!\.|\()/', '// TODO 9.0 migration: Line %LINE: You may need to rewrite "VARIABLE.hiddenInIndex" to VARIABLE.property(\'hiddenInMenu\'). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        $this->replaceEelExpression('/\.property\([\'"]_hiddenInIndex[\'"]\)/', '.property(\'hiddenInMenu\')');
        $this->fusionFlowQueryNodePropertyToWarningComment('_hiddenInIndex', 'Line %LINE: !! You very likely need to rewrite "q(VARIABLE).property("_hiddenInIndex")" to "VARIABLE.property(\'hiddenInMenu\')". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // getAccessRoles todo add warning
        // getPath
        // Rewrite node.path and q(node).property("_path") to Neos.Node.path(node)
        $this->replaceEelExpression('/(node|documentNode|site)\.path\b(?!\.|\()/', 'Neos.Node.path($1)');
        $this->addCommentsIfRegexMatches('/\.path\b(?!\.|\()/', '// TODO 9.0 migration: Line %LINE: You may need to rewrite "VARIABLE.path" to Neos.Node.path(VARIABLE). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        $this->replaceEelExpression('/q\(([^)]+)\)\.property\([\'"]_path[\'"]\)/', 'Neos.Node.path($1)');
        $this->fusionFlowQueryNodePropertyToWarningComment('_path', 'Line %LINE: !! You very likely need to rewrite "q(VARIABLE).property("_path")" to "Neos.Node.path(VARIABLE)". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // getContextPath
        // Rewrite node.contextPath to Neos.Node.serializedNodeAddress(node)
        $this->replaceEelExpression('/(node|documentNode|site)\.contextPath\b(?!\.|\()/', 'Neos.Node.serializedNodeAddress($1)');
        $this->addCommentsIfRegexMatches('/\.contextPath\b(?!\.|\()/', '// TODO 9.0 migration: Line %LINE: !! You very likely need to rewrite "VARIABLE.contextPath" to "Neos.Node.serializedNodeAddress(VARIABLE)". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        $this->replaceEelExpression('/q\(([^)]+)\)\.property\([\'"]_contextPath[\'"]\)/', 'Neos.Node.serializedNodeAddress($1)');
        $this->fusionFlowQueryNodePropertyToWarningComment('_contextPath', 'Line %LINE: !! You very likely need to rewrite "q(VARIABLE).property("_contextPath")" to "Neos.Node.serializedNodeAddress(VARIABLE)". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // getDepth
        // Rewrite node.depth and q(node).property("_depth") to Neos.Node.depth(node)
        $this->replaceEelExpression('/([a-zA-Z.]+)?(site|node|documentNode)\.depth\b(?!\.|\()/', 'Neos.Node.depth($1$2)');
        $this->addCommentsIfRegexMatches('/\.depth\b(?!\.|\()/', '// TODO 9.0 migration: Line %LINE: You may need to rewrite "VARIABLE.depth" to Neos.Node.depth(VARIABLE). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        $this->replaceEelExpression('/q\(([^)]+)\)\.property\([\'"]_depth[\'"]\)/', 'Neos.Node.depth($1)');
        $this->fusionFlowQueryNodePropertyToWarningComment('_depth', 'Line %LINE: !! You very likely need to rewrite "q(VARIABLE).property("_depth")" to "Neos.Node.depth(VARIABLE)". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // getWorkspace
        // todo refactor workspace.name to workspaceName
        $this->fusionFlowQueryNodePropertyToWarningComment('_workspace', 'Line %LINE: !! You very likely need to rewrite "q(VARIABLE).property("_workspace")". It does not make sense anymore concept-wise. In Neos < 9, it pointed to the workspace where the node was *at home at*. Now, the closest we have here is the node identity.');
        // getIdentifier
        // Rewrite "node.identifier" and "q(node).property('_identifier')" to "node.aggregateId"
        $this->replaceEelExpression('/(node|documentNode|site)\.identifier/', '$1.aggregateId');
        $this->addCommentsIfRegexMatches('/\.identifier/', '// TODO 9.0 migration: Line %LINE: You may need to rewrite "VARIABLE.identifier" to "VARIABLE.aggregateId". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        $this->replaceEelExpression('/q\(([^)]+)\)\.property\([\'"]_identifier[\'"]\)/', '$1.aggregateId');
        $this->fusionFlowQueryNodePropertyToWarningComment('_identifier', 'Line %LINE: !! You very likely need to rewrite "q(VARIABLE).property("_identifier")" to "VARIABLE.aggregateId". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // getIndex
        $this->fusionFlowQueryNodePropertyToWarningComment('_index', 'Line %LINE: !! You very likely need to rewrite "q(VARIABLE).property("_index")". You can fetch all siblings and inspect the ordering.');
        // getParent -> Node
        // Rewrite node.parent to q(node).parent().get(0)
        $this->replaceEelExpression('/(node|documentNode)\.parent/', 'q($1).parent().get(0)');
        $this->addCommentsIfRegexMatches('/\.parent($|[^a-z(])/i', '// TODO 9.0 migration: Line %LINE: You may need to rewrite "VARIABLE.parent" to "q(VARIABLE).parent().get(0)". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        $this->fusionFlowQueryNodePropertyToWarningComment('_parent', 'Line %LINE: !! You very likely need to rewrite "q(VARIABLE).property("_parent")" to "q(VARIABLE).parent().get(0)". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // getParentPath - deprecated todo warning
        // getPrimaryChildNode() - deprecated todo warning
        $this->fusionNodePropertyPathToWarningComment('removed', 'Line %LINE: !! node.removed - the new CR *never* returns removed nodes; so you can simplify your code and just assume removed == FALSE in all scenarios.');
        // isVisible() todo
        // isAccessible() todo
        // hasAccessRestrictions() todo
        // getNodeData() todo warning
        // getContext() todo warning when just passing around
        // getDimensions() TODO: Fusion
        // isAutoCreated()
        // Rewrite node.autoCreated to node.classification.tethered
        $this->replaceEelExpression('/(node|documentNode|site)\.autoCreated/', '$1.classification.tethered');
        $this->addCommentsIfRegexMatches('/\.autoCreated/', '// TODO 9.0 migration: Line %LINE: !! You very likely need to rewrite "VARIABLE.autoCreated" to "VARIABLE.classification.tethered". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        $this->fusionFlowQueryNodePropertyToWarningComment('_autoCreated', 'Line %LINE: !! You very likely need to rewrite "q(VARIABLE).property("_autoCreated")" to "VARIABLE.classification.tethered". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');

        // getOtherNodeVariants() TODO: Fusion?

        /**
         * Neos\ContentRepository\Domain\Projection\Content\NodeInterface
         */
        // isRoot() todo
        // isTethered() todo
        // getContentStreamIdentifier() -> threw exception in <= Neos 8.0 - so nobody could have used this
        // getNodeAggregateIdentifier()
        // Rewrite node.nodeAggregateIdentifier to node.aggregateId
        $this->replaceEelExpression('/(node|documentNode|site)\.nodeAggregateIdentifier/', '$1.aggregateId');
        $this->addCommentsIfRegexMatches('/\.nodeAggregateIdentifier/', '// TODO 9.0 migration: Line %LINE: You may need to rewrite "VARIABLE.nodeAggregateIdentifier" to VARIABLE.aggregateId. We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // getNodeTypeName() compatible with property access
        // getNodeName() todo rename to node.name
        // getOriginDimensionSpacePoint() -> threw exception in <= Neos 8.0 - so nobody could have used this

        /**
         * Neos\ContentRepository\Core\NodeType\NodeType
         */
        // getName()
        // Rewrite node.nodeType.name to node.nodeTypeName
        $this->replaceEelExpression('/(node|documentNode|site)\.nodeType\.name/', '$1.nodeTypeName');
        $this->addCommentsIfRegexMatches('/\.nodeType.name/', '// TODO 9.0 migration: Line %LINE: You may need to rewrite "VARIABLE.nodeType.name" to "VARIABLE.nodeTypeName". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');

        /**
         * Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface
         */
        // no methods for fusion access

        /**
         * Context
         */
        // Context::getWorkspaceName()
        // Rewrite "node.context.workspaceName" to "node.workspaceName"
        $this->replaceEelExpression('/(node|documentNode|site)\.context\.(workspaceName|workspace\.name)\b/', '$1.workspaceName');
        $this->addCommentsIfRegexMatches('/\.context\.workspaceName/', '// TODO 9.0 migration: Line %LINE: You very likely need to rewrite "VARIABLE.context.workspaceName" to "VARIABLE.workspaceName". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // Context::getRootNode()
        $this->fusionNodePropertyPathToWarningComment('context.rootNode', 'Line %LINE: !! node.context.rootNode is removed in Neos 9.0.');
        // getCurrentDateTime(): DateTime|DateTimeInterface
        $this->fusionNodePropertyPathToWarningComment('context.currentDateTime', 'Line %LINE: !! node.context.currentDateTime is removed in Neos 9.0.');
        // getDimensions(): array
        $this->fusionNodePropertyPathToWarningComment('context.dimensions', 'Line %LINE: !! node.context.dimensions is removed in Neos 9.0. You can get node DimensionSpacePoints via node.dimensionSpacePoints now or use the `Neos.Dimension.*` helper.');
        // getProperties(): array
        $this->fusionNodePropertyPathToWarningComment('context.properties', 'Line %LINE: !! node.context.properties is removed in Neos 9.0.');
        // getTargetDimensions(): array
        $this->fusionNodePropertyPathToWarningComment('context.targetDimensions', 'Line %LINE: !! node.context.targetDimensions is removed in Neos 9.0.');
        // getTargetDimensionValues(): array
        $this->fusionNodePropertyPathToWarningComment('context.targetDimensionValues', 'Line %LINE: !! node.context.targetDimensionValues is removed in Neos 9.0.');
        // getWorkspace([createWorkspaceIfNecessary: bool = true]): Workspace
        // Add comment to "node.context.workspace"
        $this->addCommentsIfRegexMatches('/\.context\.workspace(\.\w)?\b/', '// TODO 9.0 migration: Line %LINE: You very likely need to rewrite "VARIABLE.context.workspace" as the "context" of nodes has been removed without a direct replacement in Neos 9. If you really need the workspace in fusion you need to create a dedicated helper yourself which should ideally do ALL the complex logic in php directly and return the computed result.');
        // isInaccessibleContentShown(): bool
        $this->fusionNodePropertyPathToWarningComment('context.isInaccessibleContentShown', 'Line %LINE: !! node.context.isInaccessibleContentShown is removed in Neos 9.0.');
        // isInvisibleContentShown(): bool
        $this->fusionNodePropertyPathToWarningComment('context.isInvisibleContentShown', 'Line %LINE: !! node.context.isInvisibleContentShown is removed in Neos 9.0.');
        // isRemovedContentShown(): bool
        $this->fusionNodePropertyPathToWarningComment('context.isRemovedContentShown', 'Line %LINE: !! node.context.isRemovedContentShown is removed in Neos 9.0.');

        /**
         * ContentContext
         */
        // ContentContext::getCurrentSite
        // Rewrite node.context.currentSite to Neos.Site.findBySiteNode(site)
        $this->replaceEelExpression('/(node|documentNode|site|[a-zA-Z]+)\.context\.currentSite\b/', 'Neos.Site.findBySiteNode(site)');
        // ContentContext::getCurrentDomain
        $this->fusionNodePropertyPathToWarningComment('context.currentDomain', 'Line %LINE: !! node.context.currentDomain is removed in Neos 9.0.');
        // ContentContext::getCurrentSiteNode
        $this->fusionNodePropertyPathToWarningComment('context.currentSiteNode', 'Line %LINE: !! node.context.currentSiteNode is removed in Neos 9.0. Check if you can\'t simply use ${site}.');
        // ContentContext::isLive
        // Rewrite "node.context.live" to "!renderingMode.isEdit"
        $this->replaceEelExpression('/(node|documentNode|site)\.context\.live/', '!renderingMode.isEdit');
        $this->addCommentsIfRegexMatches('/\.context\.live/', '// TODO 9.0 migration: Line %LINE: You very likely need to rewrite "VARIABLE.context.live" to "!renderingMode.isEdit". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // ContentContext::isInBackend
        // Rewrite "node.context.inBackend" to "renderingMode.isEdit"
        $this->replaceEelExpression('/(node|documentNode|site)\.context\.inBackend/', 'renderingMode.isEdit');
        $this->addCommentsIfRegexMatches('/\.context\.inBackend/', '// TODO 9.0 migration: Line %LINE: You very likely need to rewrite "VARIABLE.context.inBackend" to "renderingMode.isEdit". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');
        // ContentContext::getCurrentRenderingMode... -> renderingMode...
        // Rewrite node.context.currentRenderingMode... to renderingMode...
        $this->replaceEelExpression('/(node|documentNode|site)\.context\.currentRenderingMode\.(name|title|fusionPath|options)/', 'renderingMode.$2');
        $this->replaceEelExpression('/(node|documentNode|site)\.context\.currentRenderingMode\.edit/', 'renderingMode.isEdit');
        $this->replaceEelExpression('/(node|documentNode|site)\.context\.currentRenderingMode\.preview/', 'renderingMode.isPreview');
        $this->addCommentsIfRegexMatches('/\.context\.currentRenderingMode/', '// TODO 9.0 migration: Line %LINE: You very likely need to rewrite "VARIABLE.context.currentRenderingMode..." to "renderingMode...". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.');

        /**
         * CacheLifetimeOperation and caching
         */
        // Add comment if .cacheLifetime() is used.
        $this->addCommentsIfRegexMatches('/\.cacheLifetime()/', '// TODO 9.0 migration: Line %LINE: You may need to remove ".cacheLifetime()" as this FlowQuery Operation has been removed. This is not needed anymore as the concept of timeable node visibility has changed. See https://github.com/neos/timeable-node-visibility');
        // Rewrite node to Neos.Caching.entryIdentifierForNode(...) in @cache.entryIdentifier segments
        /* todo
        function (string $eelExpression, FusionPath $path) {
            if (!$path->containsSegments('__meta', 'cache', 'entryIdentifier')) {
                return $eelExpression;
            }
            return preg_replace(
                '/(?<!Neos\.Caching\.entryIdentifierForNode\()(node|documentNode|site)/',
                'Neos.Caching.entryIdentifierForNode($1)',
                $eelExpression
            );
        }*/

        /**
         * Neos.Fusion:Attributes
         */
        // todo $rectorConfig->ruleWithConfiguration(FusionPrototypeNameAddCommentRector::class, [
        //     new FusionPrototypeNameAddComment('Neos.Fusion:Attributes', 'TODO 9.0 migration: Neos.Fusion:Attributes has been removed without a replacement. You need to replace it by the property attributes in "Neos.Fusion:Tag" or apply the Eel helper "Neos.Array.toHtmlAttributesString(attributes)".')
        // ]);

        /**
         * FlowQuery Operation context()
         */
        // Add comments for q(node).context({targetDimensions|currentDateTime|removedContentShown|inaccessibleContentShown: ...})
        $this->addCommentsIfRegexMatches('/context\(\s*\{(.*)[\'"](targetDimensions|currentDateTime|removedContentShown|inaccessibleContentShown)[\'"](.*)\}\s*\)/', '// TODO 9.0 migration: Line %LINE: The "context()" FlowQuery operation has changed and does not support the following properties anymore: targetDimensions,currentDateTime,removedContentShown,inaccessibleContentShown.');
    }
}
