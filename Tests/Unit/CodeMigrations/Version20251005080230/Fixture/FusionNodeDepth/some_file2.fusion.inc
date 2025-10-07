prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {

  renderer = Neos.Fusion:Component {

    #
    # pass down props
    #
    attributes = ${this.node.depth || this.documentNode.depth}

    renderer = afx`
      <input
        type="checkbox"
        name={node.depth}
        value={someOtherVariable.depth}
        checked={props.checked}
        {...node.depth}
      />
    `
  }
}
-----
// TODO 9.0 migration: Line 15: You may need to rewrite "VARIABLE.depth" to Neos.Node.depth(VARIABLE). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.
prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {

  renderer = Neos.Fusion:Component {

    #
    # pass down props
    #
    attributes = ${Neos.Node.depth(this.node) || Neos.Node.depth(this.documentNode)}

    renderer = afx`
      <input
        type="checkbox"
        name={Neos.Node.depth(node)}
        value={someOtherVariable.depth}
        checked={props.checked}
        {...Neos.Node.depth(node)}
      />
    `
  }
}
