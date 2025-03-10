@contentrepository @adapters=DoctrineDBAL
@flowEntities
Feature: Soft remove node aggregate with node without dimensions

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

    Then the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId            | nodeName   | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                       |
      | sir-david-nodenborough     | node       | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {}                                                          |
      | nody-mc-nodeface           | child-node | sir-david-nodenborough | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Nody Mc Nodeface"}           |
      | sir-nodeward-nodington-iii | esquire    | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington III"} |
    And the command CreateNodeVariant is executed with payload:
      | Key             | Value                    |
      | nodeAggregateId | "sir-david-nodenborough" |
      | sourceOrigin    | {"language":"de"}        |
      | targetOrigin    | {"language":"gsw"}       |

    Then I am in dimension space point {"language": "fr"}
    And the command CreateNodeVariant is executed with payload:
      | Key             | Value                    |
      | nodeAggregateId | "sir-david-nodenborough" |
      | sourceOrigin    | {"language":"de"}        |
      | targetOrigin    | {"language":"fr"}        |
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId           | nodeName  | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                      |
      | sir-nodeward-nodington-iv | bukara    | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington IV"} |
      | sir-nodeward-nodington-v  | tinquarto | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington V"}  |

    Then the command CreateWorkspace is executed with payload:
      | Key                | Value            |
      | workspaceName      | "user-workspace" |
      | baseWorkspaceName  | "live"           |
      | newContentStreamId | "user-cs-id"     |
    And I am in workspace "user-workspace"
    And I am in dimension space point {"language": "de"}

  Scenario: Soft remove node aggregate in user-workspace
    Given the command TagSubtree is executed with payload:
      | Key                          | Value                |
      | workspaceName                | "user-workspace"     |
      | nodeAggregateId              | "nody-mc-nodeface"   |
      | coveredDimensionSpacePoint   | {"language": "de"}   |
      | nodeVariantSelectionStrategy | "allSpecializations" |
      | tag                          | "removed"            |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId  | created | changed | moved | deleted | originDimensionSpacePoint |
      | nody-mc-nodeface | 0       | 0       | 0     | 1       | {"language": "de"}        |
      | nody-mc-nodeface | 0       | 0       | 0     | 1       | {"language": "gsw"}       |
    And I expect the ChangeProjection to have no changes in "cs-identifier"

  Scenario: Soft remove node aggregate in user-workspace
    Given the command TagSubtree is executed with payload:
      | Key                          | Value                |
      | workspaceName                | "user-workspace"     |
      | nodeAggregateId              | "nody-mc-nodeface"   |
      | coveredDimensionSpacePoint   | {"language": "de"}   |
      | nodeVariantSelectionStrategy | "allSpecializations" |
      | tag                          | "removed"            |

    Given the command UntagSubtree is executed with payload:
      | Key                          | Value                |
      | workspaceName                | "user-workspace"     |
      | nodeAggregateId              | "nody-mc-nodeface"   |
      | coveredDimensionSpacePoint   | {"language": "de"}   |
      | nodeVariantSelectionStrategy | "allSpecializations" |
      | tag                          | "removed"            |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId  | created | changed | moved | deleted | originDimensionSpacePoint |
      | nody-mc-nodeface | 0       | 1       | 0     | 0       | {"language": "de"}        |
      | nody-mc-nodeface | 0       | 1       | 0     | 0       | {"language": "gsw"}       |
    And I expect the ChangeProjection to have no changes in "cs-identifier"

  Scenario: Soft remove node aggregate in live workspace
    Given the command TagSubtree is executed with payload:
      | Key                          | Value                |
      | workspaceName                | "live"               |
      | nodeAggregateId              | "nody-mc-nodeface"   |
      | coveredDimensionSpacePoint   | {"language": "de"}   |
      | nodeVariantSelectionStrategy | "allSpecializations" |
      | tag                          | "removed"            |

    Then I expect the ChangeProjection to have no changes in "cs-identifier"
    And I expect the ChangeProjection to have no changes in "user-cs-id"

  Scenario: Soft remove node aggregate with children in user workspace with "allSpecializations"
    Given the command TagSubtree is executed with payload:
      | Key                          | Value                    |
      | workspaceName                | "user-workspace"         |
      | nodeAggregateId              | "sir-david-nodenborough" |
      | coveredDimensionSpacePoint   | {"language": "de"}       |
      | nodeVariantSelectionStrategy | "allSpecializations"     |
      | tag                          | "removed"                |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId        | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough | 0       | 0       | 0     | 1       | {"language": "de"}        |
      | sir-david-nodenborough | 0       | 0       | 0     | 1       | {"language": "gsw"}       |
    And I expect the ChangeProjection to have no changes in "cs-identifier"

  Scenario: Soft remove node aggregate with children in user workspace with "allVariants"
    Given the command TagSubtree is executed with payload:
      | Key                          | Value                    |
      | workspaceName                | "user-workspace"         |
      | nodeAggregateId              | "sir-david-nodenborough" |
      | coveredDimensionSpacePoint   | {"language": "de"}       |
      | nodeVariantSelectionStrategy | "allVariants"            |
      | tag                          | "removed"                |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId        | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough | 0       | 0       | 0     | 1       | {"language": "de"}        |
      | sir-david-nodenborough | 0       | 0       | 0     | 1       | {"language": "gsw"}       |
      | sir-david-nodenborough | 0       | 0       | 0     | 1       | {"language": "fr"}        |
    And I expect the ChangeProjection to have no changes in "cs-identifier"

  Scenario: Soft remove node aggregate with children in live workspace
    Given the command TagSubtree is executed with payload:
      | Key                          | Value                    |
      | workspaceName                | "live"                   |
      | nodeAggregateId              | "sir-david-nodenborough" |
      | coveredDimensionSpacePoint   | {"language": "de"}       |
      | nodeVariantSelectionStrategy | "allSpecializations"     |
      | tag                          | "removed"                |

    Then I expect the ChangeProjection to have no changes in "cs-identifier"
    And I expect the ChangeProjection to have no changes in "user-cs-id"

  Scenario: Soft remove node aggregate in user workspace which was already modified with "allSpecializations"
    Given the command SetNodeProperties is executed with payload:
      | Key                       | Value                    |
      | workspaceName             | "user-workspace"         |
      | nodeAggregateId           | "sir-david-nodenborough" |
      | originDimensionSpacePoint | {"language": "de"}       |
      | propertyValues            | {"text": "Other text"}   |
    And    the command SetNodeProperties is executed with payload:
      | Key                       | Value                    |
      | workspaceName             | "user-workspace"         |
      | nodeAggregateId           | "sir-david-nodenborough" |
      | originDimensionSpacePoint | {"language": "fr"}       |
      | propertyValues            | {"text": "Other text"}   |

    And the command TagSubtree is executed with payload:
      | Key                          | Value                    |
      | workspaceName                | "user-workspace"         |
      | nodeAggregateId              | "sir-david-nodenborough" |
      | coveredDimensionSpacePoint   | {"language": "de"}       |
      | nodeVariantSelectionStrategy | "allSpecializations"     |
      | tag                          | "removed"                |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId        | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough | 0       | 1       | 0     | 1       | {"language": "de"}        |
      | sir-david-nodenborough | 0       | 0       | 0     | 1       | {"language": "gsw"}       |
      | sir-david-nodenborough | 0       | 1       | 0     | 0       | {"language": "fr"}        |
    And I expect the ChangeProjection to have no changes in "cs-identifier"

  Scenario: Soft remove node aggregate in user workspace which was already modified with "allVariants"
    Given the command SetNodeProperties is executed with payload:
      | Key                       | Value                    |
      | workspaceName             | "user-workspace"         |
      | nodeAggregateId           | "sir-david-nodenborough" |
      | originDimensionSpacePoint | {"language": "de"}       |
      | propertyValues            | {"text": "Other text"}   |
    And    the command SetNodeProperties is executed with payload:
      | Key                       | Value                    |
      | workspaceName             | "user-workspace"         |
      | nodeAggregateId           | "sir-david-nodenborough" |
      | originDimensionSpacePoint | {"language": "fr"}       |
      | propertyValues            | {"text": "Other text"}   |

    And the command TagSubtree is executed with payload:
      | Key                          | Value                    |
      | workspaceName                | "user-workspace"         |
      | nodeAggregateId              | "sir-david-nodenborough" |
      | coveredDimensionSpacePoint   | {"language": "de"}       |
      | nodeVariantSelectionStrategy | "allVariants"            |
      | tag                          | "removed"                |

    Then I expect the ChangeProjection to have the following changes in "user-cs-id":
      | nodeAggregateId        | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough | 0       | 1       | 0     | 1       | {"language": "de"}        |
      | sir-david-nodenborough | 0       | 0       | 0     | 1       | {"language": "gsw"}       |
      | sir-david-nodenborough | 0       | 1       | 0     | 1       | {"language": "fr"}        |
    And I expect the ChangeProjection to have no changes in "cs-identifier"
