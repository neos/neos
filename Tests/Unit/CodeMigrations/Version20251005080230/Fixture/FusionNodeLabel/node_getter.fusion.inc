prototype(Neos.Rector:Test)  < prototype(Neos.Fusion:Value) {
  node = ${node.label || documentNode.label || site.label}
  otherVariable = ${someOtherVariable.label}
  inAfx = afx`<Neos.Fusion:Value value={node.label}/>`
}
-----
// TODO 9.0 migration: Line 4: You very likely need to rewrite "VARIABLE.label" to "Neos.Node.label(VARIABLE)". We did not auto-apply this migration because we cannot be sure whether the variable is a Node.
prototype(Neos.Rector:Test)  < prototype(Neos.Fusion:Value) {
  node = ${Neos.Node.label(node) || Neos.Node.label(documentNode) || Neos.Node.label(site)}
  otherVariable = ${someOtherVariable.label}
  inAfx = afx`<Neos.Fusion:Value value={Neos.Node.label(node)}/>`
}
