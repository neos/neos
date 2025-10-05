prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {

    renderer = Neos.Fusion:Component {

        #
        # pass down props
        #
        attributes = ${node.contextPath || documentNode.contextPath || q(node).property('_contextPath') || q(documentNode).property("_contextPath")}
        foo = ${q(bar).property('_contextPath') || q(bar).property("_contextPath")}
        boo = ${q(nodes).first().property('_contextPath') || q(nodes).first().property("_contextPath")}

        #
        # the `checked` state is calculated outside the renderer to allow` overriding via `attributes`
        #
        checked = false
        checked.@process.checkMultiValue = ${Array.indexOf(field.getCurrentMultivalueStringified(), field.getTargetValueStringified()) > -1}
        checked.@process.checkMultiValue.@if.hasValue = ${field.hasCurrentValue()}
        checked.@process.checkMultiValue.@if.isMultiple = ${field.isMultiple()}
        checked.@process.checkSingleValue = ${field.getCurrentValueStringified() == field.getTargetValueStringified()}
        checked.@process.checkSingleValue.@if.hasValue = ${field.hasCurrentValue()}
        checked.@process.checkSingleValue.@if.isSingle = ${!field.isMultiple()}

        renderer = afx`
        <input
                type="checkbox"
                name={node.contextPath}
                value={someOtherVariable.contextPath}
                checked={props.checked}
                {...node.contextPath}
        />
        `
    }
}
-----
// TODO 9.0 migration: Line 12: !! You very likely need to rewrite "q(VARIABLE).property('_contextPath')" to "Neos.Node.serializedNodeAddress(VARIABLE)". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.
// TODO 9.0 migration: Line 28: !! You very likely need to rewrite "VARIABLE.contextPath" to "Neos.Node.serializedNodeAddress(VARIABLE)". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.
prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {

    renderer = Neos.Fusion:Component {

        #
        # pass down props
        #
        attributes = ${Neos.Node.serializedNodeAddress(node) || Neos.Node.serializedNodeAddress(documentNode) || Neos.Node.serializedNodeAddress(node) || Neos.Node.serializedNodeAddress(documentNode)}
        foo = ${Neos.Node.serializedNodeAddress(bar) || Neos.Node.serializedNodeAddress(bar)}
        boo = ${q(nodes).first().property('_contextPath') || q(nodes).first().property("_contextPath")}

        #
        # the `checked` state is calculated outside the renderer to allow` overriding via `attributes`
        #
        checked = false
        checked.@process.checkMultiValue = ${Array.indexOf(field.getCurrentMultivalueStringified(), field.getTargetValueStringified()) > -1}
        checked.@process.checkMultiValue.@if.hasValue = ${field.hasCurrentValue()}
        checked.@process.checkMultiValue.@if.isMultiple = ${field.isMultiple()}
        checked.@process.checkSingleValue = ${field.getCurrentValueStringified() == field.getTargetValueStringified()}
        checked.@process.checkSingleValue.@if.hasValue = ${field.hasCurrentValue()}
        checked.@process.checkSingleValue.@if.isSingle = ${!field.isMultiple()}

        renderer = afx`
        <input
                type="checkbox"
                name={Neos.Node.serializedNodeAddress(node)}
                value={someOtherVariable.contextPath}
                checked={props.checked}
                {...Neos.Node.serializedNodeAddress(node)}
        />
        `
    }
}
