prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {

    empty = ${q(node).context()}
    workspaceName = ${q(node).context({'workspaceName': 'other-workspace'})}
    dimensions = ${q(node).context({"dimensions": {'language': ['de']}})}
    invisibleContentShown = ${q(node).context({'invisibleContentShown': true})}

    currentDateTime = ${q(node).context({'currentDateTime': 'other-workspace'})}
    targetDimensions = ${q(node).context({"targetDimensions": {'language': ['de']}})}
    removedContentShown = ${q(node).context({'removedContentShown': true})}
    inaccessibleContentShown = ${q(node).context({"inaccessibleContentShown": true})}

    mixedRemovedContentShown = ${q(node).context({'workspaceName': 'other-workspace', 'removedContentShown': true})}
    mixedTargetDimensions = ${q(node).context({'targetDimensions': {'language': ['de']}, 'dimensions': {'language': ['de']}})}

    variables = ${q(site).context({'dimensions': this.dimensions.dimensions, 'targetDimensions': this.dimensions.targetDimensions})}

}
-----
// TODO 9.0 migration: Line 15: The "context()" FlowQuery operation has changed and does not support the following properties anymore: targetDimensions,currentDateTime,removedContentShown,inaccessibleContentShown.
// TODO 9.0 migration: Line 16: The "context()" FlowQuery operation has changed and does not support the following properties anymore: targetDimensions,currentDateTime,removedContentShown,inaccessibleContentShown.
// TODO 9.0 migration: Line 17: The "context()" FlowQuery operation has changed and does not support the following properties anymore: targetDimensions,currentDateTime,removedContentShown,inaccessibleContentShown.
// TODO 9.0 migration: Line 18: The "context()" FlowQuery operation has changed and does not support the following properties anymore: targetDimensions,currentDateTime,removedContentShown,inaccessibleContentShown.
// TODO 9.0 migration: Line 20: The "context()" FlowQuery operation has changed and does not support the following properties anymore: targetDimensions,currentDateTime,removedContentShown,inaccessibleContentShown.
// TODO 9.0 migration: Line 21: The "context()" FlowQuery operation has changed and does not support the following properties anymore: targetDimensions,currentDateTime,removedContentShown,inaccessibleContentShown.
// TODO 9.0 migration: Line 23: The "context()" FlowQuery operation has changed and does not support the following properties anymore: targetDimensions,currentDateTime,removedContentShown,inaccessibleContentShown.
prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {

    empty = ${q(node).context()}
    workspaceName = ${q(node).context({'workspaceName': 'other-workspace'})}
    dimensions = ${q(node).context({"dimensions": {'language': ['de']}})}
    invisibleContentShown = ${q(node).context({'invisibleContentShown': true})}

    currentDateTime = ${q(node).context({'currentDateTime': 'other-workspace'})}
    targetDimensions = ${q(node).context({"targetDimensions": {'language': ['de']}})}
    removedContentShown = ${q(node).context({'removedContentShown': true})}
    inaccessibleContentShown = ${q(node).context({"inaccessibleContentShown": true})}

    mixedRemovedContentShown = ${q(node).context({'workspaceName': 'other-workspace', 'removedContentShown': true})}
    mixedTargetDimensions = ${q(node).context({'targetDimensions': {'language': ['de']}, 'dimensions': {'language': ['de']}})}

    variables = ${q(site).context({'dimensions': this.dimensions.dimensions, 'targetDimensions': this.dimensions.targetDimensions})}

}
