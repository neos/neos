@flowEntities
Feature: TODO

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
    And using no content dimensions
    And using the following node types:
    """yaml
    'Neos.ContentRepository.Testing:Document': {}
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And I am user identified by "initiating-user-identifier"
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
      | nodeAggregateId | nodeTypeName                            | parentNodeAggregateId | nodeName |
      | a               | Neos.ContentRepository.Testing:Document | root                  | a        |
      | a1              | Neos.ContentRepository.Testing:Document | a                     | a1       |
      | a1a             | Neos.ContentRepository.Testing:Document | a1                    | a1a      |
      | a1a1            | Neos.ContentRepository.Testing:Document | a1a                   | a1a1     |
      | a1a1a           | Neos.ContentRepository.Testing:Document | a1a1                  | a1a1a    |
      | a1a1b           | Neos.ContentRepository.Testing:Document | a1a1                  | a1a1b    |
      | a1a2            | Neos.ContentRepository.Testing:Document | a1a                   | a1a2     |
      | a1b             | Neos.ContentRepository.Testing:Document | a1                    | a1b      |
      | a2              | Neos.ContentRepository.Testing:Document | a                     | a2       |
      | b               | Neos.ContentRepository.Testing:Document | root                  | b        |
      | b1              | Neos.ContentRepository.Testing:Document | b                     | b1       |
    And the following Neos users exist:
      | Id      | Username | First name | Last name | Roles                                            |
      | janedoe | jane.doe | Jane       | Doe       | Neos.Neos:Administrator                          |
      | johndoe | john.doe | John       | Doe       | Neos.Neos:RestrictedEditor,Neos.Neos:UserManager |
      | editor  | editor   | Edward     | Editor    | Neos.Neos:Editor                                 |

  Scenario: Access content graph for root workspace without role assignments
    Given I am in workspace "live"
    And the command TagSubtree is executed with payload:
      | Key                          | Value                |
      | nodeAggregateId              | "a"                  |
      | nodeVariantSelectionStrategy | "allSpecializations" |
      | tag                          | "blog"               |
    And the role VIEWER is assigned to workspace "live" for group "Neos.Flow:Everybody"
    When content repository security is enabled
    Then The user "john.doe" should not be able to read node "a1"
    Then The user "jane.doe" should be able to read node "a1"

  Scenario: TODO
    When content repository security is enabled
    And the user "jane.doe" accesses the content graph for workspace "live"
    Then an exception 'Read access denied for workspace "live": Account "jane.doe" is a Neos Administrator without explicit role for workspace "live"' should be thrown

  Scenario: TODO
    Given the role MANAGER is assigned to workspace "live" for user "janedoe"
    When content repository security is enabled
    And the user "jane.doe" accesses the content graph for workspace "live"
    Then no exception should be thrown
