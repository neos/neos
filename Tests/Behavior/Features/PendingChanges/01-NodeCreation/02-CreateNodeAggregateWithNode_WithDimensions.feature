# @contentrepository @adapters=DoctrineDBAL
@flowEntities
Feature: Create node aggregate with node with dimensions

  Background:
    Given using the following content dimensions:
      | Identifier | Values    | Generalizations |
      | language   | de,gsw,fr | gsw->de, fr     |
    And using the following node types:
    """yaml
    'Neos.ContentRepository:Root': {}
    'Neos.Neos:Sites':
      superTypes:
        'Neos.ContentRepository:Root': true
    'Neos.Neos:Site':
      superTypes:
        'Neos.Neos:Document': true
    'Neos.Neos:Document':
      properties:
        title:
          type: string
        uriPathSegment:
          type: string
    'Neos.Neos:Test.DocumentType':
      superTypes:
        'Neos.Neos:Document': true
      # todo enable
      # childNodes:
      #   main:
      #     type: 'Neos.Neos:ContentCollection'
    'Neos.Neos:Content': {}
    'Neos.Neos:ContentCollection': {}
    'Neos.Neos:Test.ContentType':
      superTypes:
        'Neos.Neos:Content': true
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And the command CreateRootWorkspace is executed with payload:
      | Key                  | Value                |
      | workspaceName        | "live"               |
      | workspaceTitle       | "Live"               |
      | workspaceDescription | "The live workspace" |
      | newContentStreamId   | "cs-identifier"      |

    And I am in workspace "live"
    And I am in dimension space point {"language": "de"}
    And I am user identified by "initiating-user-identifier"
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value             |
      | nodeAggregateId | "root"            |
      | nodeTypeName    | "Neos.Neos:Sites" |

    And the command CreateNodeAggregateWithNode is executed with payload:
      | Key                   | Value            |
      | nodeAggregateId       | "site"           |
      | nodeTypeName          | "Neos.Neos:Site" |
      | parentNodeAggregateId | "root"           |
      | nodeName              | "site"           |

    And the command CreateNodeVariant is executed with payload:
      | Key             | Value             |
      | nodeAggregateId | "site"            |
      | sourceOrigin    | {"language":"de"} |
      | targetOrigin    | {"language":"fr"} |

    When the command CreateWorkspace is executed with payload:
      | Key                | Value            |
      | workspaceName      | "user-workspace" |
      | baseWorkspaceName  | "live"           |
      | newContentStreamId | "user-cs-id"     |

  Scenario: Nodes on live workspace have been created
    Given I am in workspace "live"

    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId        | parentNodeAggregateId  | nodeTypeName                | initialPropertyValues                              |
      | sir-david-nodenborough | site                   | Neos.Neos:Test.DocumentType | {}                                                 |
      | nody-mc-nodeface       | sir-david-nodenborough | Neos.Neos:Test.DocumentType | {"title": "This is a text about Nody Mc Nodeface"} |

    Then I am in dimension space point {"language": "fr"}
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId            | parentNodeAggregateId | nodeTypeName                | initialPropertyValues                                               |
      | sir-nodeward-nodington-iii | site                  | Neos.Neos:Test.DocumentType | {"title": "This is a french text about Sir Nodeward Nodington III"} |

    Then I expect to have no changes in workspace "live"

  Scenario: Nodes on user workspace have been created
    Given I am in workspace "user-workspace"

    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId           | parentNodeAggregateId  | nodeTypeName                | initialPropertyValues                                       |
      | sir-david-nodenborough    | site                   | Neos.Neos:Test.DocumentType | {}                                                          |
      | nody-mc-nodeface          | sir-david-nodenborough | Neos.Neos:Test.DocumentType | {"title": "This is a text about Nody Mc Nodeface"}          |
      | sir-nodeward-nodington-iv | site                   | Neos.Neos:Test.DocumentType | {"title": "This is a text about Sir Nodeward Nodington IV"} |

    Then I am in dimension space point {"language": "fr"}
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId            | parentNodeAggregateId | nodeTypeName                | initialPropertyValues                                                 |
      | sir-nodeward-nodington-iii | site                  | Neos.Neos:Test.DocumentType | {"title": "This is a extended text about Sir Nodeward Nodington III"} |

    Then I expect to have the following changes in workspace "user-workspace":
      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough     | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iv  | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {"language":"fr"}         |
    And I expect to have no changes in workspace "live"

    When I publish the 1 changes in document "sir-david-nodenborough" from workspace "user-workspace" to "live"

    Then I expect that the following node events have been published
      | type                            | event payload                                                                              |
      | NodeAggregateWithNodeWasCreated | {"nodeAggregateId":"sir-david-nodenborough","originDimensionSpacePoint":{"language":"de"}} |

    Then I expect that the following node events are kept as remainder
      | type                            | event payload                                                                                  |
      | NodeAggregateWithNodeWasCreated | {"nodeAggregateId":"nody-mc-nodeface","originDimensionSpacePoint":{"language":"de"}}           |
      | NodeAggregateWithNodeWasCreated | {"nodeAggregateId":"sir-nodeward-nodington-iv","originDimensionSpacePoint":{"language":"de"}}  |
      | NodeAggregateWithNodeWasCreated | {"nodeAggregateId":"sir-nodeward-nodington-iii","originDimensionSpacePoint":{"language":"fr"}} |
