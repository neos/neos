prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {

  renderer = Neos.Fusion:Component {

    #
    # pass down props
    #
    attributes = ${node.depth || documentNode.depth || q(node).property('_depth') || q(documentNode).property("_depth")}
    foo = ${q(bar).property('_depth') || q(bar).property("_depth")}
    boo = ${q(nodes).first().property('_depth') || q(nodes).first().property("_depth")}

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
        name={node.depth}
        value={someOtherVariable.depth || something}
        path={someOtherVariable.depth}
        checked={props.checked}
        {...node.depth}
      />
    `
  }
}
-----
// TODO 9.0 migration: Line 13: You may need to rewrite "q(VARIABLE).property('_depth')" to Neos.Node.depth(VARIABLE). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.
// TODO 9.0 migration: Line 29: You may need to rewrite "VARIABLE.depth" to Neos.Node.depth(VARIABLE). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.
// TODO 9.0 migration: Line 30: You may need to rewrite "VARIABLE.depth" to Neos.Node.depth(VARIABLE). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.
prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {

  renderer = Neos.Fusion:Component {

    #
    # pass down props
    #
    attributes = ${Neos.Node.depth(node) || Neos.Node.depth(documentNode) || Neos.Node.depth(node) || Neos.Node.depth(documentNode)}
    foo = ${Neos.Node.depth(bar) || Neos.Node.depth(bar)}
    boo = ${q(nodes).first().property('_depth') || q(nodes).first().property("_depth")}

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
        name={Neos.Node.depth(node)}
        value={someOtherVariable.depth || something}
        path={someOtherVariable.depth}
        checked={props.checked}
        {...Neos.Node.depth(node)}
      />
    `
  }
}
