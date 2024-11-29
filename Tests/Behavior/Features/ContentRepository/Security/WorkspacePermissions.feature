@flowEntities
Feature: Workspace permission related features

  Background:
    When using the following content dimensions:
      | Identifier | Values                | Generalizations                     |
      | language   | mul, de, en, gsw, ltz | ltz->de->mul, gsw->de->mul, en->mul |
    And using the following node types:
    """yaml
    'Neos.Neos:Document':
      properties:
        foo:
          type: string
      references:
        ref: []
    'Neos.Neos:Document2': {}
    'Neos.Neos:CustomRoot':
      superTypes:
        'Neos.ContentRepository:Root': true
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And the command CreateRootWorkspace is executed with payload:
      | Key                | Value           |
      | workspaceName      | "live"          |
      | newContentStreamId | "cs-identifier" |
    And I am in workspace "live" and dimension space point {}
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                         |
      | nodeAggregateId | "root"                        |
      | nodeTypeName    | "Neos.ContentRepository:Root" |
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId | nodeTypeName       | parentNodeAggregateId | nodeName | originDimensionSpacePoint |
      | a               | Neos.Neos:Document | root                  | a        | {"language":"mul"}        |
      | a1              | Neos.Neos:Document | a                     | a1       | {"language":"de"}         |
      | a1a             | Neos.Neos:Document | a1                    | a1a      | {"language":"de"}         |
      | a1a1            | Neos.Neos:Document | a1a                   | a1a1     | {"language":"de"}         |
      | a1a1a           | Neos.Neos:Document | a1a1                  | a1a1a    | {"language":"de"}         |
      | a1a1b           | Neos.Neos:Document | a1a1                  | a1a1b    | {"language":"de"}         |
      | a1a2            | Neos.Neos:Document | a1a                   | a1a2     | {"language":"de"}         |
      | a1b             | Neos.Neos:Document | a1                    | a1b      | {"language":"de"}         |
      | a2              | Neos.Neos:Document | a                     | a2       | {"language":"de"}         |
      | b               | Neos.Neos:Document | root                  | b        | {"language":"de"}         |
      | b1              | Neos.Neos:Document | b                     | b1       | {"language":"de"}         |
    And the following Neos users exist:
      | Username          | Roles                      |
      | admin             | Neos.Neos:Administrator    |
      | editor            | Neos.Neos:Editor           |
      | restricted_editor | Neos.Neos:RestrictedEditor |
      | owner             | Neos.Neos:Editor           |
      | manager           | Neos.Neos:Editor           |
      | collaborator      | Neos.Neos:Editor           |
      | uninvolved        | Neos.Neos:Editor           |
    And I am in workspace "live"
    And I am in dimension space point {"language":"de"}
    And the command TagSubtree is executed with payload:
      | Key                          | Value                |
      | nodeAggregateId              | "a"                  |
      | nodeVariantSelectionStrategy | "allSpecializations" |
      | tag                          | "subtree_a"          |
    And the command DisableNodeAggregate is executed with payload:
      | Key                          | Value         |
      | nodeAggregateId              | "a1a1a"       |
      | nodeVariantSelectionStrategy | "allVariants" |
    And the personal workspace "workspace" is created with the target workspace "live" for user "owner"
    And I am in workspace "workspace"
    And the role MANAGER is assigned to workspace "workspace" for user "manager"
    And the role COLLABORATOR is assigned to workspace "workspace" for user "collaborator"
    # The following step was added in order to make the `AddDimensionShineThrough` command viable
    And I change the content dimensions in content repository "default" to:
      | Identifier | Values      | Generalizations |
      | language   | mul, de, ch | ch->de->mul     |
    And content repository security is enabled

  Scenario Outline: Creating a root workspace
    Given I am authenticated as <user>
    When the command CreateRootWorkspace is executed with payload '{"workspaceName":"new-ws","newContentStreamId":"new-cs"}' and exceptions are caught
    Then the last command should have thrown an exception of type "AccessDenied" with code 1729086686

    Examples:
      | user              |
      | admin             |
      | editor            |
      | restricted_editor |
      | owner             |
      | collaborator      |
      | uninvolved        |

  Scenario Outline: Creating a base workspace without WRITE permissions
    Given I am authenticated as <user>
    And the shared workspace "some-shared-workspace" is created with the target workspace "workspace"
    Then an exception of type "AccessDenied" should be thrown with code 1729086686

    And the personal workspace "some-other-personal-workspace" is created with the target workspace "workspace" for user <user>
    Then an exception of type "AccessDenied" should be thrown with code 1729086686

    Examples:
      | user              |
      | admin             |
      | editor            |
      | restricted_editor |
      | uninvolved        |

  Scenario Outline: Creating a base workspace with WRITE permissions
    Given I am authenticated as <user>
    And the shared workspace "some-shared-workspace" is created with the target workspace "workspace"

    And the personal workspace "some-other-personal-workspace" is created with the target workspace "workspace" for user <user>

    Examples:
      | user         |
      | collaborator |
      | owner        |

  Scenario Outline: Deleting a workspace without MANAGE permissions
    Given I am authenticated as <user>
    When the command DeleteWorkspace is executed with payload '{"workspaceName":"workspace"}' and exceptions are caught
    Then the last command should have thrown an exception of type "AccessDenied" with code 1729086686

    Examples:
      | user         |
      | collaborator |
      | uninvolved   |

  Scenario Outline: Deleting a workspace with MANAGE permissions
    Given I am authenticated as <user>
    When the command DeleteWorkspace is executed with payload '{"workspaceName":"workspace"}'

    Examples:
      | user    |
      | admin   |
      | manager |
      | owner   |

  Scenario Outline: Managing metadata and roles of a workspace without MANAGE permissions
    Given I am authenticated as <user>
    And the title of workspace "workspace" is set to "Some new workspace title"
    Then an exception of type "AccessDenied" should be thrown with code 1731654519

    And the description of workspace "workspace" is set to "Some new workspace description"
    Then an exception of type "AccessDenied" should be thrown with code 1731654519

    When the role COLLABORATOR is assigned to workspace "workspace" for group "Neos.Neos:AbstractEditor"
    Then an exception of type "AccessDenied" should be thrown with code 1731654519

    When the role for group "Neos.Neos:AbstractEditor" is unassigned from workspace "workspace"
    Then an exception of type "AccessDenied" should be thrown with code 1731654519

    Examples:
      | user         |
      | collaborator |
      | uninvolved   |

  Scenario Outline: Managing metadata and roles of a workspace with MANAGE permissions
    Given I am authenticated as <user>
    And the title of workspace "workspace" is set to "Some new workspace title"
    And the description of workspace "workspace" is set to "Some new workspace description"
    When the role COLLABORATOR is assigned to workspace "workspace" for group "Neos.Neos:AbstractEditor"
    When the role for group "Neos.Neos:AbstractEditor" is unassigned from workspace "workspace"

    Examples:
      | user    |
      | admin   |
      | manager |
      | owner   |

  Scenario Outline: Handling commands that require WRITE permissions on the workspace
    When I am authenticated as "uninvolved"
    And the command <command> is executed with payload '<command payload>' and exceptions are caught
    Then the last command should have thrown an exception of type "AccessDenied" with code 1729086686

    When I am authenticated as "editor"
    And the command <command> is executed with payload '<command payload>' and exceptions are caught
    Then the last command should have thrown an exception of type "AccessDenied" with code 1729086686

    When I am authenticated as "restricted_editor"
    And the command <command> is executed with payload '<command payload>' and exceptions are caught
    Then the last command should have thrown an exception of type "AccessDenied" with code 1729086686

    When I am authenticated as "admin"
    And the command <command> is executed with payload '<command payload>' and exceptions are caught
    Then the last command should have thrown an exception of type "AccessDenied" with code 1729086686

    When I am authenticated as "owner"
    And the command <command> is executed with payload '<command payload>'

    # todo test also collaborator, but cannot commands twice here:
    # When I am authenticated as "collaborator"
    # And the command <command> is executed with payload '<command payload>' and exceptions are caught

    Examples:
      | command                             | command payload                                                                                        |
      | CreateNodeAggregateWithNode         | {"nodeAggregateId":"a1b1","parentNodeAggregateId":"a1b","nodeTypeName":"Neos.Neos:Document"}           |
      | CreateNodeVariant                   | {"nodeAggregateId":"a1","sourceOrigin":{"language":"de"},"targetOrigin":{"language":"mul"}}             |
      | DisableNodeAggregate                | {"nodeAggregateId":"a1","nodeVariantSelectionStrategy":"allVariants"}                                  |
      | EnableNodeAggregate                 | {"nodeAggregateId":"a1a1a","nodeVariantSelectionStrategy":"allVariants"}                               |
      | RemoveNodeAggregate                 | {"nodeAggregateId":"a1","nodeVariantSelectionStrategy":"allVariants"}                                  |
      | TagSubtree                          | {"nodeAggregateId":"a1","tag":"some_tag","nodeVariantSelectionStrategy":"allVariants"}                 |
      | UntagSubtree                        | {"nodeAggregateId":"a","tag":"subtree_a","nodeVariantSelectionStrategy":"allVariants"}                 |
      | MoveNodeAggregate                   | {"nodeAggregateId":"a1","newParentNodeAggregateId":"b"}                                                |
      | SetNodeProperties                   | {"nodeAggregateId":"a1","propertyValues":{"foo":"bar"}}                                                |
      | SetNodeReferences                   | {"sourceNodeAggregateId":"a1","references":[{"referenceName": "ref", "references": [{"target":"b"}]}]} |

      | AddDimensionShineThrough            | {"nodeAggregateId":"a1","source":{"language":"de"},"target":{"language":"ch"}}                         |
      | ChangeNodeAggregateName             | {"nodeAggregateId":"a1","newNodeName":"changed"}                                                       |
      | ChangeNodeAggregateType             | {"nodeAggregateId":"a1","newNodeTypeName":"Neos.Neos:Document2","strategy":"happypath"}                |
      | CreateRootNodeAggregateWithNode     | {"nodeAggregateId":"c","nodeTypeName":"Neos.Neos:CustomRoot"}                                          |
      | MoveDimensionSpacePoint             | {"source":{"language":"de"},"target":{"language":"ch"}}                                                |
      | UpdateRootNodeAggregateDimensions   | {"nodeAggregateId":"root"}                                                                             |
      | DiscardWorkspace                    | {}                                                                                                     |
      | DiscardIndividualNodesFromWorkspace | {"nodesToDiscard":[{"nodeAggregateId":"a1"}]}                                                          |
      | PublishWorkspace                    | {}                                                                                                     |
      | PublishIndividualNodesFromWorkspace | {"nodesToPublish":[{"nodeAggregateId":"a1"}]}                                                          |
      | RebaseWorkspace                     | {}                                                                                                     |
      | CreateWorkspace                     | {"workspaceName":"new-workspace","baseWorkspaceName":"workspace","newContentStreamId":"any"}           |

