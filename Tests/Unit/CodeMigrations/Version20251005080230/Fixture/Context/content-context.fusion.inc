prototype(Neos.Context:Prototype) < prototype(Neos.Fusion:Component) {
    inBackend = ${node.context.inBackend}
}

-----
prototype(Neos.Context:Prototype) < prototype(Neos.Fusion:Component) {
    inBackend = ${renderingMode.isEdit}
}
