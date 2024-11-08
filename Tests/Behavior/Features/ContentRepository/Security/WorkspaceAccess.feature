@flowEntities
Feature: Workspace access related features

  Background:
    Given The following additional policies are configured:
      """
      privilegeTargets:
        'Neos\Neos\Security\Authorization\Privilege\ReadNodePrivilege':
          'Neos.Neos:ReadBlog':
            matcher: 'blog'
      roles:
        'Neos.Neos:Administrator':
          privileges:
            - privilegeTarget: 'Neos.Neos:ReadBlog'
              permission: GRANT
      """
    And using the following content dimensions:
      | Identifier | Values                | Generalizations                     |
      | language   | mul, de, en, gsw, ltz | ltz->de->mul, gsw->de->mul, en->mul |
    And using the following node types:
    """yaml
    'Neos.Neos:Document': {}
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And I am user identified by "initiating-user-identifier"
    And the command CreateRootWorkspace is executed with payload:
      | Key                | Value           |
      | workspaceName      | "live"          |
      | newContentStreamId | "cs-identifier" |
    And I am in workspace "live" and dimension space point {}
    And the following Neos users exist:
      | Username          | Roles                                            |
      | admin             | Neos.Neos:Administrator                          |
      | editor            | Neos.Neos:Editor                                 |
      | restricted_editor | Neos.Neos:RestrictedEditor,Neos.Neos:UserManager |
      | no_editor         |                                                  |

  Scenario: TODO
    When content repository security is enabled
    And I am authenticated as "admin"
    And I access the content graph for workspace "live"
    Then an exception of type "AccessDenied" should be thrown with message:
    """
    Read access denied for workspace "live": Account "admin" is a Neos Administrator without explicit role for workspace "live"
    """

  Scenario: TODO
    Given the role MANAGER is assigned to workspace "live" for user "admin"
    When content repository security is enabled
    And I am authenticated as "admin"
    And I access the content graph for workspace "live"
    Then no exception should be thrown

  Scenario Outline: Accessing content graph for explicitly assigned workspace role to the authenticated user
    Given the role <workspace role> is assigned to workspace "live" for user "<user>"
    When content repository security is enabled
    And I am authenticated as "<user>"
    And I access the content graph for workspace "live"
    Then no exception should be thrown

    Examples:
      | user              | workspace role |
      | admin             | VIEWER         |
      | editor            | COLLABORATOR   |
      | editor            | VIEWER         |
      | restricted_editor | MANAGER        |
      | restricted_editor | VIEWER         |

  Scenario Outline: Accessing content graph for workspace role assigned to group of the authenticated user
    Given the role <workspace role> is assigned to workspace "live" for group "<group>"
    When content repository security is enabled
    And I am authenticated as "<user>"
    And I access the content graph for workspace "live"
    Then no exception should be thrown

    Examples:
      | user              | group                      | workspace role |
      | admin             | Neos.Neos:Editor           | COLLABORATOR   |
      | editor            | Neos.Neos:Editor           | COLLABORATOR   |
      | restricted_editor | Neos.Neos:RestrictedEditor | VIEWER         |
      | no_editor         | Neos.Flow:Everybody        | VIEWER         |

  Scenario Outline: Accessing content graph for workspace role assigned to group the authenticated user is not part of
    Given the role <workspace role> is assigned to workspace "live" for group "<group>"
    When content repository security is enabled
    And I am authenticated as "<user>"
    And I access the content graph for workspace "live"
    Then an exception of type "AccessDenied" should be thrown

    Examples:
      | user              | group                   | workspace role |
      | admin             | Neos.Flow:Anonymous     | COLLABORATOR   |
      | editor            | Neos.Neos:Administrator | MANAGER        |
      | restricted_editor | Neos.Neos:Editor        | VIEWER         |

  Scenario Outline: Accessing content graph for workspace that is owned by the authenticated user
    Given the personal workspace "user-workspace" is created with the target workspace "live" for user "<user>"
    When content repository security is enabled
    And I am authenticated as "<user>"
    And I access the content graph for workspace "user-workspace"
    Then no exception should be thrown

    Examples:
      | user              |
      | admin             |
      | editor            |
      | restricted_editor |
