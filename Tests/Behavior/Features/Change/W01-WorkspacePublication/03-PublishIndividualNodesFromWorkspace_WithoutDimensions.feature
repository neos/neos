@contentrepository @adapters=DoctrineDBAL
@flowEntities
Feature: Publish nodes partially without dimensions

  Background:
    Given using no content dimensions
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
    And I am in dimension space point {}
    And I am user identified by "initiating-user-identifier"
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                         |
      | nodeAggregateId | "lady-eleonode-rootford"      |
      | nodeTypeName    | "Neos.ContentRepository:Root" |

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
    And I am in dimension space point {}

    Then the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId            | nodeName   | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                       |
      | sir-david-nodenborough     | node       | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {}                                                          |
      | nody-mc-nodeface           | child-node | sir-david-nodenborough | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Nody Mc Nodeface"}           |
      | sir-nodeward-nodington-iii | esquire    | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington III"} |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough     | 1       | 1       | 0     | 0       | {}                        |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {}                        |
      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {}                        |

    When the command PublishIndividualNodesFromWorkspace is executed with payload:
      | Key                             | Value                                                                                                         |
      | nodesToPublish                  | [{"workspaceName": "user-workspace", "dimensionSpacePoint": {}, "nodeAggregateId": "sir-david-nodenborough"}] |
      | contentStreamIdForRemainingPart | "user-cs-id-remaining"                                                                                        |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id-remaining":
      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {}                        |
      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {}                        |
    And I expect the ChangeProjection to have no changes in "user-cs-id"
    And I expect the ChangeProjection to have no changes in "cs-identifier"

  Scenario: Publish nodes partially from user workspace to a non live workspace
    Given the command CreateWorkspace is executed with payload:
      | Key                | Value              |
      | workspaceName      | "review-workspace" |
      | baseWorkspaceName  | "live"             |
      | newContentStreamId | "review-cs-id"     |

    And the command RebaseWorkspace is executed with payload:
      | Key           | Value              |
      | workspaceName | "review-workspace" |

    When I am in workspace "review-workspace"

    And I am in dimension space point {}
    Then the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId        | nodeName | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues |
      | sir-david-nodenborough | node     | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {}                    |

    And the command CreateWorkspace is executed with payload:
      | Key                | Value              |
      | workspaceName      | "user-workspace"   |
      | baseWorkspaceName  | "review-workspace" |
      | newContentStreamId | "user-cs-id"       |

    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |

    And I am in workspace "user-workspace"

    And I am in dimension space point {}
    Then the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId            | nodeName   | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                       |
      | nody-mc-nodeface           | child-node | sir-david-nodenborough | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Nody Mc Nodeface"}           |
      | sir-nodeward-nodington-iii | esquire    | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington III"} |

    Then I expect the ChangeProjection to have the following changes in "review-cs-id":
      | nodeAggregateId        | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough | 1       | 1       | 0     | 0       | {}                        |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
      | nody-mc-nodeface           | 1       | 1       | 0     | 0       | {}                        |
      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {}                        |

    When the command PublishIndividualNodesFromWorkspace is executed with payload:
      | Key                             | Value                                                                                                   |
      | nodesToPublish                  | [{"workspaceName": "user-workspace", "dimensionSpacePoint": {}, "nodeAggregateId": "nody-mc-nodeface"}] |
      | contentStreamIdForRemainingPart | "user-cs-id-remaining"                                                                                  |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id-remaining":
      | nodeAggregateId            | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-nodeward-nodington-iii | 1       | 1       | 0     | 0       | {}                        |
    And I expect the ChangeProjection to have no changes in "user-cs-id"
    And I expect the ChangeProjection to have the following changes in "review-cs-id":
      | nodeAggregateId        | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough | 1       | 1       | 0     | 0       | {}                        |
      | nody-mc-nodeface       | 1       | 1       | 0     | 0       | {}                        |
    And I expect the ChangeProjection to have no changes in "cs-identifier"
