@contentrepository @adapters=DoctrineDBAL
@flowEntities
Feature: Create node aggregate with node with dimensions

  Background:
    Given using the following content dimensions:
      | Identifier | Values    | Generalizations |
      | language   | de,gsw,fr | gsw->de, fr     |
    And using the following node types:
    """yaml
    'Neos.ContentRepository.Testing:Node':
      properties:
        text:
          type: string
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
      | Key             | Value                         |
      | nodeAggregateId | "lady-eleonode-rootford"      |
      | nodeTypeName    | "Neos.ContentRepository:Root" |

    When the command CreateWorkspace is executed with payload:
      | Key                | Value            |
      | workspaceName      | "user-workspace" |
      | baseWorkspaceName  | "live"           |
      | newContentStreamId | "user-cs-id"     |
    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |
    And I am in dimension space point {"language": "de"}

  Scenario: Nodes on live workspace have been created
    Given I am in workspace "live"

    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId        | nodeName   | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                             |
      | sir-david-nodenborough | node       | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {}                                                |
      | nody-mc-nodeface       | child-node | sir-david-nodenborough | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Nody Mc Nodeface"} |

    Then I am in dimension space point {"language": "fr"}
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId            | nodeName | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                              |
      | sir-nodeward-nodington-iii | esquire  | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a french text about Sir Nodeward Nodington III"} |

    Then I expect the ChangeProjection to have the following changes in "live":
      | nodeAggregateId | created | changed | moved | deleted | originDimensionSpacePoint |


  Scenario: Nodes on user workspace have been created
    Given I am in workspace "user-workspace"

    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId           | nodeName   | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                      |
      | sir-david-nodenborough    | node       | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {}                                                         |
      | nody-mc-nodeface          | child-node | sir-david-nodenborough | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Nody Mc Nodeface"}          |
      | sir-nodeward-nodington-iv | bakura     | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington IV"} |

    Then I am in dimension space point {"language": "fr"}
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId            | nodeName | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                                |
      | sir-nodeward-nodington-iii | esquire  | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a extended text about Sir Nodeward Nodington III"} |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough     | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iv  | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {"language":"fr"}         |



#    When the command PublishIndividualNodesFromWorkspace is executed with payload:
#      | Key                             | Value                                                                                                                        |
#      | nodesToPublish                  | [{"workspaceName": "user-workspace", "dimensionSpacePoint": {"language":"de"}, "nodeAggregateId": "sir-david-nodenborough"}] |
#      | contentStreamIdForRemainingPart | "user-cs-identifier-remaining"                                                                                               |
#
#    And I expect the ChangeProjection to have the following changes in "user-cs-identifier-remaining":
#      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
#      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {"language":"de"}         |
#      | sir-nodeward-nodington-iv  | 1       | 1       | 0     | 0       | {"language":"de"}         |
#      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {"language":"fr"}         |
#
#    Then I expect the ChangeProjection to have no changes in "user-cs-id"
