prototype(Neos.Rector:Test)  < prototype(Neos.Fusion:Value) {
  node = ${node.hidden || documentNode.hidden || site.hidden}
  otherVariable = ${someOtherVariable.hidden}
  inAfx = afx`<Neos.Fusion:Value value={node.hidden}/>`
}
-----
// TODO 9.0 migration: Line 4: You may need to rewrite "VARIABLE.hidden" to Neos.Node.isDisabled(VARIABLE). We did not auto-apply this migration because we cannot be sure whether the variable is a Node.
prototype(Neos.Rector:Test)  < prototype(Neos.Fusion:Value) {
  node = ${Neos.Node.isDisabled(node) || Neos.Node.isDisabled(documentNode) || Neos.Node.isDisabled(site)}
  otherVariable = ${someOtherVariable.hidden}
  inAfx = afx`<Neos.Fusion:Value value={Neos.Node.isDisabled(node)}/>`
}
