prototype(Neos.Context:Prototype)  < prototype(Neos.Fusion:Component) {

    context = ${node.context}

    attributes = ${node.context || site.context || documentNode.context}

    renderer = afx`
        <Neos.Context:Component
            type="checkbox"
            name={node.context}
            value={someOtherVariable.context}
        />
    `
}
-----
// TODO 9.0 migration: Line 7: !! node.context is removed in Neos 9.0 and cannot be passed around. In Neos 9.0 you likely want to pass the NodeAddress, the Node around or Subgraph around
// TODO 9.0 migration: Line 9: !! node.context is removed in Neos 9.0 and cannot be passed around. In Neos 9.0 you likely want to pass the NodeAddress, the Node around or Subgraph around
// TODO 9.0 migration: Line 14: !! node.context is removed in Neos 9.0 and cannot be passed around. In Neos 9.0 you likely want to pass the NodeAddress, the Node around or Subgraph around
// TODO 9.0 migration: Line 15: !! node.context is removed in Neos 9.0 and cannot be passed around. In Neos 9.0 you likely want to pass the NodeAddress, the Node around or Subgraph around
prototype(Neos.Context:Prototype)  < prototype(Neos.Fusion:Component) {

    context = ${node.context}

    attributes = ${node.context || site.context || documentNode.context}

    renderer = afx`
        <Neos.Context:Component
            type="checkbox"
            name={node.context}
            value={someOtherVariable.context}
        />
    `
}
