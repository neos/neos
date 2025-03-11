@contentrepository @adapters=DoctrineDBAL
@flowEntities
Feature: Move DimensionSpacePoints

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

    Then I am in dimension space point {"language": "de"}
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId            | nodeName   | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                       |
      | sir-david-nodenborough     | node       | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {}                                                          |
      | nody-mc-nodeface           | child-node | sir-david-nodenborough | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Nody Mc Nodeface"}           |
      | sir-nodeward-nodington-iii | esquire    | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington III"} |

    Then I am in dimension space point {"language": "fr"}
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId           | nodeName | parentNodeAggregateId  | nodeTypeName                        | initialPropertyValues                                      |
      | sir-nodeward-nodington-iv | bakura   | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington IV"} |
      | sir-nodeward-nodington-v  | quatilde | lady-eleonode-rootford | Neos.ContentRepository.Testing:Node | {"text": "This is a text about Sir Nodeward Nodington V"}  |

    Then the command CreateWorkspace is executed with payload:
      | Key                | Value            |
      | workspaceName      | "user-workspace" |
      | baseWorkspaceName  | "live"           |
      | newContentStreamId | "user-cs-id"     |

    And I am in workspace "user-workspace"

    Then the command SetNodeProperties is executed with payload:
      | Key                       | Value                    |
      | workspaceName             | "user-workspace"         |
      | nodeAggregateId           | "sir-david-nodenborough" |
      | originDimensionSpacePoint | {"language": "de"}       |
      | propertyValues            | {"text": "Some text"}    |
    Then the command SetNodeProperties is executed with payload:
      | Key                       | Value                        |
      | workspaceName             | "user-workspace"             |
      | nodeAggregateId           | "sir-nodeward-nodington-iv"  |
      | originDimensionSpacePoint | {"language": "fr"}           |
      | propertyValues            | {"text": "Some french text"} |


    Then I expect to have the following changes in workspace "user-workspace":
      | nodeAggregateId           | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough    | 0       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iv | 0       | 1       | 0     | 0       | {"language":"fr"}         |
    And I expect to have no changes in workspace "live"

    And I am in workspace "live"

  Scenario: Rename a dimension value in live workspace
    Given I change the content dimensions in content repository "default" to:
      | Identifier | Values          | Generalizations    |
      | language   | de_DE,gsw,fr,en | gsw->de_DE->en, fr |

    And I run the following node migration for workspace "live", creating target workspace "migration" on contentStreamId "migration-cs-id", with publishing on success:
    """yaml
    migration:
      -
        transformations:
          -
            type: 'MoveDimensionSpacePoint'
            settings:
              from: {"language":"de"}
              to: {"language":"de_DE"}
    """

    Then I expect to have the following changes in workspace "user-workspace":
      | nodeAggregateId           | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough    | 0       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iv | 0       | 1       | 0     | 0       | {"language":"fr"}         |
    And I expect to have no changes in workspace "live"
    And I expect the ChangeProjection to have no changes in "migration-cs-id"


  Scenario: Rename a dimension value in user workspace
    Given I change the content dimensions in content repository "default" to:
      | Identifier | Values          | Generalizations    |
      | language   | de_DE,gsw,fr,en | gsw->de_DE->en, fr |

    And I run the following node migration for workspace "user-workspace", creating target workspace "migration" on contentStreamId "migration-cs-id", with publishing on success:
    """yaml
    migration:
      -
        transformations:
          -
            type: 'MoveDimensionSpacePoint'
            settings:
              from: {"language":"de"}
              to: {"language":"de_DE"}
    """

    Then I expect to have the following changes in workspace "user-workspace":
      | nodeAggregateId           | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough    | 0       | 1       | 0     | 0       | {"language":"de_DE"}      |
      | sir-nodeward-nodington-iv | 0       | 1       | 0     | 0       | {"language":"fr"}         |
    And I expect to have no changes in workspace "live"
    And I expect the ChangeProjection to have no changes in "migration-cs-id"


  Scenario: Adding a dimension in live workspace
    Given I change the content dimensions in content repository "default" to:
      | Identifier | Values       | Generalizations |
      | language   | de,gsw,fr,en | gsw->de->en, fr |
      | market     | DE, FR       | DE, FR          |

    And I run the following node migration for workspace "live", creating target workspace "migration-cs" on contentStreamId "migration-cs", with publishing on success:
    """yaml
    migration:
      -
        transformations:
          -
            type: 'MoveDimensionSpacePoint'
            settings:
              from: {"language":"de"}
              to: {"language":"de", "market": "DE"}
          -
            type: 'MoveDimensionSpacePoint'
            settings:
              from: {"language":"fr"}
              to: {"language":"fr", "market": "FR"}
    """

    Then I expect to have the following changes in workspace "user-workspace":
      | nodeAggregateId           | created | changed | moved | deleted | originDimensionSpacePoint |
      | sir-david-nodenborough    | 0       | 1       | 0     | 0       | {"language":"de"}         |
      | sir-nodeward-nodington-iv | 0       | 1       | 0     | 0       | {"language":"fr"}         |
    And I expect to have no changes in workspace "live"
    And I expect the ChangeProjection to have no changes in "migration-cs-id"


  Scenario: Adding a dimension in user workspace
    Given I change the content dimensions in content repository "default" to:
      | Identifier | Values       | Generalizations |
      | language   | de,gsw,fr,en | gsw->de->en, fr |
      | market     | DE, FR       | DE, FR          |

    And I run the following node migration for workspace "user-workspace", creating target workspace "migration-cs" on contentStreamId "migration-cs", with publishing on success:
    """yaml
    migration:
      -
        transformations:
          -
            type: 'MoveDimensionSpacePoint'
            settings:
              from: {"language":"de"}
              to: {"language":"de", "market": "DE"}
          -
            type: 'MoveDimensionSpacePoint'
            settings:
              from: {"language":"fr"}
              to: {"language":"fr", "market": "FR"}
    """

    Then I expect to have the following changes in workspace "user-workspace":
      | nodeAggregateId           | created | changed | moved | deleted | originDimensionSpacePoint         |
      | sir-david-nodenborough    | 0       | 1       | 0     | 0       | {"language":"de", "market": "DE"} |
      | sir-nodeward-nodington-iv | 0       | 1       | 0     | 0       | {"language":"fr", "market": "FR"} |
    And I expect to have no changes in workspace "live"
    And I expect the ChangeProjection to have no changes in "migration-cs-id"
