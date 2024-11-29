@contentrepository @adapters=DoctrineDBAL
@flowEntities
Feature: Publish nodes partially with dimensions

  Background:
    Given using the following content dimensions:
      | Identifier | Values       | Generalizations |
      | language   | de,gsw,fr,en | gsw->de->en, fr |
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

    When an asset exists with id "asset-1"
    And an asset exists with id "asset-2"
    And an asset exists with id "asset-3"

  Scenario: Publish nodes partially from user workspace to live
    Given the command CreateWorkspace is executed with payload:
      | Key                | Value            |
      | workspaceName      | "user-workspace" |
      | baseWorkspaceName  | "live"           |
      | newContentStreamId | "user-cs-id"     |
    And I am in workspace "user-workspace"
    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |

    Then I am in dimension space point {"language": "de"}
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

    When the command PublishIndividualNodesFromWorkspace is executed with payload:
      | Key                             | Value                                                                                                                         |
      | nodesToPublish                  | [{"workspaceName": "user-workspace", "dimensionSpacePoint": {"language": "de"}, "nodeAggregateId": "sir-david-nodenborough"}] |
      | contentStreamIdForRemainingPart | "user-cs-identifier-remaining"                                                                                                |

    Then I expect the ChangeProjection to have the following changes in "user-cs-identifier-remaining":
      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iv  | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {"language":"fr"}         |

    Then I expect the ChangeProjection to have no changes in "user-cs-id"

  Scenario: Publish nodes partially from user workspace to a non live workspace
    Given the command CreateWorkspace is executed with payload:
      | Key                | Value                    |
      | workspaceName      | "review-workspace"       |
      | baseWorkspaceName  | "live"                   |
      | newContentStreamId | "review-workspace-cs-id" |

    And the command RebaseWorkspace is executed with payload:
      | Key           | Value              |
      | workspaceName | "review-workspace" |

    And the command CreateWorkspace is executed with payload:
      | Key                | Value                  |
      | workspaceName      | "user-workspace"       |
      | baseWorkspaceName  | "review-workspace"     |
      | newContentStreamId | "user-cs-id" |

    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |

    And I am in workspace "user-workspace"

    Then I am in dimension space point {"language": "de"}
    And  the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId           | nodeName   | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                      |
      | sir-david-nodenborough    | node       | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {}                                                         |
      | nody-mc-nodeface          | child-node | sir-david-nodenborough | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Nody Mc Nodeface"}          |
      | sir-nodeward-nodington-iv | bakura     | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington IV"} |

    Then I am in dimension space point {"language": "gsw"}
    And the command CreateNodeVariant is executed with payload:
      | Key             | Value              |
      | nodeAggregateId | "nody-mc-nodeface" |
      | sourceOrigin    | {"language":"de"}  |
      | targetOrigin    | {"language":"gsw"} |
    And the command SetNodeProperties is executed with payload:
      | Key                       | Value                  |
      | nodeAggregateId           | "nody-mc-nodeface"     |
      | originDimensionSpacePoint | {"language":"gsw"}     |
      | propertyValues            | {"text": "Other text"} |

    And I am in dimension space point {"language": "fr"}
    Then the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId            | nodeName | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                                |
      | sir-nodeward-nodington-iii | esquire  | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a extended text about Sir Nodeward Nodington III"} |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough     | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {"language":"gsw"}        |
      | sir-nodeward-nodington-iv  | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {"language":"fr"}         |

    When the command PublishIndividualNodesFromWorkspace is executed with payload:
      | Key                             | Value                                                                                                                         |
      | nodesToPublish                  | [{"workspaceName": "user-workspace", "dimensionSpacePoint": {"language": "de"}, "nodeAggregateId": "sir-david-nodenborough"}] |
      | contentStreamIdForRemainingPart | "user-cs-identifier-remaining"                                                                                                |

    Then I expect the ChangeProjection to have the following changes in "user-cs-identifier-remaining":
      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {"language":"gsw"}        |
      | sir-nodeward-nodington-iv  | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {"language":"fr"}         |
    And I expect the ChangeProjection to have no changes in "user-cs-id"

  Scenario: Publish nodes partially from user workspace to live with new generalization
    Given the command CreateWorkspace is executed with payload:
      | Key                | Value            |
      | workspaceName      | "user-workspace" |
      | baseWorkspaceName  | "live"           |
      | newContentStreamId | "user-cs-id"     |
    And I am in workspace "user-workspace"
    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |

    Then I am in dimension space point {"language": "de"}
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId             | nodeName   | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                |
      | sir-david-nodenborough     | node       | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {}                                                          |
      | nody-mc-nodeface           | child-node | sir-david-nodenborough | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Nody Mc Nodeface"}           |
      | sir-nodeward-nodington-iii | esquire    | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington III"} |

    And the command CreateNodeVariant is executed with payload:
      | Key             | Value                    |
      | nodeAggregateId | "sir-david-nodenborough" |
      | sourceOrigin    | {"language":"de"}        |
      | targetOrigin    | {"language":"en"}        |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough     | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-david-nodenborough     | 1       | 1       | 0     | 0       | {"language":"en"}         |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {"language":"de"}         |

    When the command PublishIndividualNodesFromWorkspace is executed with payload:
      | Key                             | Value                                                                                                                                                                                                                                                     |
      | nodesToPublish                  | [{"workspaceName": "user-workspace", "dimensionSpacePoint": {"language": "de"}, "nodeAggregateId": "sir-david-nodenborough"},{"workspaceName": "user-workspace", "dimensionSpacePoint": {"language": "en"}, "nodeAggregateId": "sir-david-nodenborough"}] |
      | contentStreamIdForRemainingPart | "user-cs-identifier-remaining"                                                                                                                                                                                                                            |

    Then I expect the ChangeProjection to have the following changes in "user-cs-identifier-remaining":
      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {"language":"de"}         |
    And I expect the ChangeProjection to have no changes in "user-cs-id"
