@fixtures
Feature: Tests for the EEL helpers interacting with the CR

  Background:
    Given I have the site "a"
    Given I have the site "b"
    And I have the following NodeTypes configuration:
    """yaml
    'unstructured': {}
    'Neos.Neos:FallbackNode': {}
    'Neos.Neos:Document':
      properties:
        title:
          type: string
        uriPathSegment:
          type: string
    'Neos.Neos:Content':
      properties:
        title:
          type: string
    'Neos.Neos:Test.Site':
      superTypes:
        'Neos.Neos:Document': true
    'Neos.Neos:Test.DocumentType1':
      ui:
        label: "My Document 1"
      superTypes:
        'Neos.Neos:Document': true
    'Neos.Neos:Test.DocumentType2':
      superTypes:
        'Neos.Neos:Document': true
    'Neos.Neos:Test.DocumentType2a':
      superTypes:
        'Neos.Neos:Test.DocumentType2': true
    'Neos.Neos:Test.Content':
      superTypes:
        'Neos.Neos:Content': true
    """
    And I have the following nodes:
      | Identifier | Path                       | Node Type                     | Properties                                         | Hidden in index |
      | root       | /sites                     | unstructured                  |                                                    | false           |
      | a          | /sites/a                   | Neos.Neos:Test.Site           | {"uriPathSegment": "a", "title": "Node a"}         | false           |
      | a1         | /sites/a/a1                | Neos.Neos:Test.DocumentType1  | {"uriPathSegment": "a1", "title": "Node a1"}       | false           |
      | a1a        | /sites/a/a1/a1a            | Neos.Neos:Test.DocumentType2a | {"uriPathSegment": "a1a", "title": "Node a1a"}     | false           |
      | a1a1       | /sites/a/a1/a1a/a1a1       | Neos.Neos:Test.DocumentType1  | {"uriPathSegment": "a1a1", "title": "Node a1a1"}   | false           |
      | a1a2       | /sites/a/a1/a1a/a1a2       | Neos.Neos:Test.DocumentType2  | {"uriPathSegment": "a1a2", "title": "Node a1a2"}   | false           |
      | a1a3       | /sites/a/a1/a1a/a1a3       | Neos.Neos:Test.DocumentType2a | {"uriPathSegment": "a1a3", "title": "Node a1a3"}   | false           |
      | a1a4       | /sites/a/a1/a1a/a1a4       | Neos.Neos:Test.DocumentType2a | {"uriPathSegment": "a1a4", "title": "Node a1a4"}   | false           |
      | a1a5       | /sites/a/a1/a1a/a1a5       | Neos.Neos:Test.DocumentType2a | {"uriPathSegment": "a1a5", "title": "Node a1a5"}   | false           |
      | a1a6       | /sites/a/a1/a1a/a1a6       | Neos.Neos:Test.DocumentType2  | {"uriPathSegment": "a1a6", "title": "Node a1a6"}   | false           |
      | a1a7       | /sites/a/a1/a1a/a1a7       | Neos.Neos:Test.DocumentType1  | {"uriPathSegment": "a1a7", "title": "Node a1a7"}   | false           |
      | a1b        | /sites/a/a1/a1b            | Neos.Neos:Test.DocumentType1  | {"uriPathSegment": "a1b", "title": "Node a1b"}     | false           |
      | a1b1       | /sites/a/a1/a1b/a1b1       | Neos.Neos:Test.DocumentType1  | {"uriPathSegment": "a1b1", "title": "Node a1b1"}   | false           |
      | a1b1a      | /sites/a/a1/a1b/a1b1/a1b1a | Neos.Neos:Test.DocumentType2a | {"uriPathSegment": "a1b1a", "title": "Node a1b1a"} | false           |
      | a1b1b      | /sites/a/a1/a1b/a1b1/a1b1b | Neos.Neos:Test.DocumentType1  | {"uriPathSegment": "a1b1b", "title": "Node a1b1b"} | false           |
      | a1b2       | /sites/a/a1/a1b/a1b2       | Neos.Neos:Test.DocumentType2  | {"uriPathSegment": "a1b2", "title": "Node a1b2"}   | false           |
      | a1b3       | /sites/a/a1/a1b/a1b3       | Neos.Neos:Test.DocumentType1  | {"uriPathSegment": "a1b3", "title": "Node a1b3"}   | false           |
      | a1c        | /sites/a/a1/a1c            | Neos.Neos:Test.DocumentType1  | {"uriPathSegment": "a1c", "title": "Node a1c"}     | true            |
      | a1c1       | /sites/a/a1/a1c/a1c1       | Neos.Neos:Test.DocumentType1  | {"uriPathSegment": "a1c1", "title": "Node a1c1"}   | false           |
      | b          | /sites/b                   | Neos.Neos:Test.Site           | {"uriPathSegment": "b", "title": "Node b"}         | false           |
    And the Fusion context request URI is "http://localhost"
    And I have the following Fusion setup:
    """fusion
    include: resource://Neos.Fusion/Private/Fusion/Root.fusion
    """

  Scenario: Neos.Site.findBySiteNode()
    When the Fusion context node is "a"
    When I execute the following Fusion code:
    """fusion
    test = Neos.Fusion:DataStructure {
      siteEntity_nodeName = ${Neos.Site.findBySiteNode(node).nodeName}
      siteEntity_title = ${Neos.Site.findBySiteNode(node).name}
      siteEntity_packageKey = ${Neos.Site.findBySiteNode(node).siteResourcesPackageKey}

      foreignSiteEntity = ${Neos.Site.findBySiteNode(q(node).find('#b').get(0)).nodeName}

      notASiteNode = ${Neos.Site.findBySiteNode(q(node).children().get(0))}
      @process.render = ${Json.stringify(value, ['JSON_PRETTY_PRINT'])}
    }
    """
    Then I expect the following Fusion rendering result:
    """
    {
        "siteEntity_nodeName": "a",
        "siteEntity_title": "Untitled Site",
        "siteEntity_packageKey": "Neos.Demo",
        "foreignSiteEntity": "b",
        "notASiteNode": null
    }
    """

  Scenario: Neos.Node.nodeType()
    When the Fusion context node is "a1"
    When I execute the following Fusion code:
    """fusion
    test = Neos.Fusion:DataStructure {
      nodeType_name = ${Neos.Node.nodeType(node).name}
      nodeType_label = ${Neos.Node.nodeType(node).label}
      nodeTypeName = ${node.nodeTypeName}
      @process.render = ${Json.stringify(value, ['JSON_PRETTY_PRINT'])}
    }
    """
    Then I expect the following Fusion rendering result:
    """
    {
        "nodeType_name": "Neos.Neos:Test.DocumentType1",
        "nodeType_label": "My Document 1",
        "nodeTypeName": "Neos.Neos:Test.DocumentType1"
    }
    """
    # if the node type config is empty, the label rendering should still work
    Given I have the following NodeTypes configuration:
    """yaml
    unstructured: {}
    Neos.Neos:FallbackNode: {}
    """
    When I execute the following Fusion code:
    """fusion
    test = Neos.Fusion:DataStructure {
      nodeType = ${Neos.Node.nodeType(node)}
      nodeType_name = ${Neos.Node.nodeType(node).name}
      nodeType_label = ${Neos.Node.nodeType(node).label}
      nodeTypeName = ${node.nodeTypeName}
      @process.render = ${Json.stringify(value, ['JSON_PRETTY_PRINT'])}
    }
    """
    Then I expect the following Fusion rendering result:
    """
    {
        "nodeType": null,
        "nodeType_name": null,
        "nodeType_label": null,
        "nodeTypeName": "Neos.Neos:FallbackNode"
    }
    """
