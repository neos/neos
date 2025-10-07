prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {
  renderer = Neos.Fusion:Component {
    attributes = ${node.context.currentSite.siteResourcesPackageKey || site.context.currentSite.siteResourcesPackageKey || documentNode.context.currentSite.siteResourcesPackageKey}
    renderer = afx`
      <input
        name={node.context.currentSite.siteResourcesPackageKey}
        value={someOtherVariable.context.currentSite.siteResourcesPackageKey}
        {...node.context.currentSite.siteResourcesPackageKey}
      />
    `
  }
}
-----
prototype(Neos.Fusion.Form:Checkbox)  < prototype(Neos.Fusion.Form:Component.Field) {
  renderer = Neos.Fusion:Component {
    attributes = ${Neos.Site.findBySiteNode(site).siteResourcesPackageKey || Neos.Site.findBySiteNode(site).siteResourcesPackageKey || Neos.Site.findBySiteNode(site).siteResourcesPackageKey}
    renderer = afx`
      <input
        name={Neos.Site.findBySiteNode(site).siteResourcesPackageKey}
        value={Neos.Site.findBySiteNode(site).siteResourcesPackageKey}
        {...Neos.Site.findBySiteNode(site).siteResourcesPackageKey}
      />
    `
  }
}
