prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {

    renderer = Neos.Fusion:Component {

        #
        # pass down props
        #
        attributes = ${node.autoCreated || documentNode.autoCreated}

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
                name={node.autoCreated}
                value={someOtherVariable.autoCreated}
                checked={props.checked}
                {...node.autoCreated}
        />
        `
    }
}
-----
// TODO 9.0 migration: Line 26: !! You very likely need to rewrite "VARIABLE.autoCreated" to "VARIABLE.classification.tethered". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.
prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {

    renderer = Neos.Fusion:Component {

        #
        # pass down props
        #
        attributes = ${node.classification.tethered || documentNode.classification.tethered}

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
                name={node.classification.tethered}
                value={someOtherVariable.autoCreated}
                checked={props.checked}
                {...node.classification.tethered}
        />
        `
    }
}
