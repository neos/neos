Feature: Tests for soft removal garbage collection objections

  Background:
    Given using the following content dimensions:
      | Identifier | Values                         | Generalizations                         |
      | example    | general, source, special, peer | special->source->general, peer->general |
    And using the following node types:
    """yaml
    'Neos.ContentRepository:Root': {}
    'Neos.Neos:Sites':
      superTypes:
        'Neos.ContentRepository:Root': true
    'Neos.Neos:Document':
      properties:
        title:
          type: string
        uriPathSegment:
          type: string
      references:
        myReference:
          constraints:
            nodeTypes:
              'Neos.Neos:Document': true
    'Neos.Neos:OtherDocument':
      superTypes:
        'Neos.Neos:Document': true
    'Neos.Neos:Site':
      superTypes:
        'Neos.Neos:Document': true
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And I am user identified by "initiating-user-identifier"

    When the command CreateRootWorkspace is executed with payload:
      | Key                | Value           |
      | workspaceName      | "live"          |
      | newContentStreamId | "cs-identifier" |
    And I am in workspace "live" and dimension space point {"example": "source"}
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                    |
      | nodeAggregateId | "lady-eleonode-rootford" |
      | nodeTypeName    | "Neos.Neos:Sites"        |
    And the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId        | parentNodeAggregateId  | nodeTypeName       |
      | sir-david-nodenborough | lady-eleonode-rootford | Neos.Neos:Site     |
      | nody-mc-nodeface       | sir-david-nodenborough | Neos.Neos:Document |
      | nodingers-cat          | sir-david-nodenborough | Neos.Neos:Document |
      | nodingers-kitten       | nodingers-cat          | Neos.Neos:Document |
    And the command CreateWorkspace is executed with payload:
      | Key                | Value            |
      | workspaceName      | "user-workspace" |
      | baseWorkspaceName  | "live"           |
      | newContentStreamId | "user-cs-id"     |


  # ChangeNodeAggregateName conflict prevention
  Scenario: Garbage collection will ignore a soft removal if the node has an unpublished name change
    When the command TagSubtree is executed with payload:
      | Key                          | Value                 |
      | workspaceName                | "live"                |
      | nodeAggregateId              | "nodingers-cat"       |
      | coveredDimensionSpacePoint   | {"example": "source"} |
      | nodeVariantSelectionStrategy | "allSpecializations"  |
      | tag                          | "removed"             |

    And I am in workspace "user-workspace"
    When the command ChangeNodeAggregateName is executed with payload and exceptions are caught:
      | Key             | Value           |
      | nodeAggregateId | "nodingers-cat" |
      | newNodeName     | "new-name"      |
    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |
    And soft removal garbage collection is run for content repository default

    Then I expect exactly 7 events to be published on stream "ContentStream:cs-identifier"
    And event at index 6 is of type "SubtreeWasTagged" with payload:
      | Key                          | Expected                                        |
      | workspaceName                | "live"                                          |
      | contentStreamId              | "cs-identifier"                                 |
      | nodeAggregateId              | "nodingers-cat"                                 |
      | affectedDimensionSpacePoints | [{"example": "source"}, {"example": "special"}] |
      | tag                          | "removed"                                       |

  # ChangeNodeAggregateName conflict prevention (descendants)
  Scenario: Garbage collection will ignore a soft removal if a descendant of the node has an unpublished name change
    When the command TagSubtree is executed with payload:
      | Key                          | Value                 |
      | workspaceName                | "live"                |
      | nodeAggregateId              | "nodingers-cat"       |
      | coveredDimensionSpacePoint   | {"example": "source"} |
      | nodeVariantSelectionStrategy | "allSpecializations"  |
      | tag                          | "removed"             |

    And I am in workspace "user-workspace"
    When the command ChangeNodeAggregateName is executed with payload and exceptions are caught:
      | Key             | Value              |
      | nodeAggregateId | "nodingers-kitten" |
      | newNodeName     | "new-name"         |
    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |
    And soft removal garbage collection is run for content repository default

    Then I expect exactly 7 events to be published on stream "ContentStream:cs-identifier"
    And event at index 6 is of type "SubtreeWasTagged" with payload:
      | Key                          | Expected                                        |
      | workspaceName                | "live"                                          |
      | contentStreamId              | "cs-identifier"                                 |
      | nodeAggregateId              | "nodingers-cat"                                 |
      | affectedDimensionSpacePoints | [{"example": "source"}, {"example": "special"}] |
      | tag                          | "removed"                                       |

  # ChangeNodeAggregateType conflict prevention
  Scenario: Garbage collection will ignore a soft removal if the node has an unpublished type change
    When the command TagSubtree is executed with payload:
      | Key                          | Value                 |
      | workspaceName                | "live"                |
      | nodeAggregateId              | "nodingers-cat"       |
      | coveredDimensionSpacePoint   | {"example": "source"} |
      | nodeVariantSelectionStrategy | "allSpecializations"  |
      | tag                          | "removed"             |

    And I am in workspace "user-workspace"
    When the command ChangeNodeAggregateType is executed with payload and exceptions are caught:
      | Key             | Value                     |
      | nodeAggregateId | "nodingers-cat"           |
      | newNodeTypeName | "Neos.Neos:OtherDocument" |
      | strategy        | "happypath"               |
    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |
    And soft removal garbage collection is run for content repository default

    Then I expect exactly 7 events to be published on stream "ContentStream:cs-identifier"
    And event at index 6 is of type "SubtreeWasTagged" with payload:
      | Key                          | Expected                                        |
      | workspaceName                | "live"                                          |
      | contentStreamId              | "cs-identifier"                                 |
      | nodeAggregateId              | "nodingers-cat"                                 |
      | affectedDimensionSpacePoints | [{"example": "source"}, {"example": "special"}] |
      | tag                          | "removed"                                       |

  # ChangeNodeAggregateType conflict prevention (descendants)
  Scenario: Garbage collection will ignore a soft removal if a descendant of the node has an unpublished type change
    When the command TagSubtree is executed with payload:
      | Key                          | Value                 |
      | workspaceName                | "live"                |
      | nodeAggregateId              | "nodingers-cat"       |
      | coveredDimensionSpacePoint   | {"example": "source"} |
      | nodeVariantSelectionStrategy | "allSpecializations"  |
      | tag                          | "removed"             |

    And I am in workspace "user-workspace"
    When the command ChangeNodeAggregateType is executed with payload and exceptions are caught:
      | Key             | Value                     |
      | nodeAggregateId | "nodingers-cat"           |
      | newNodeTypeName | "Neos.Neos:OtherDocument" |
      | strategy        | "happypath"               |
    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |
    And soft removal garbage collection is run for content repository default

    Then I expect exactly 7 events to be published on stream "ContentStream:cs-identifier"
    And event at index 6 is of type "SubtreeWasTagged" with payload:
      | Key                          | Expected                                        |
      | workspaceName                | "live"                                          |
      | contentStreamId              | "cs-identifier"                                 |
      | nodeAggregateId              | "nodingers-cat"                                 |
      | affectedDimensionSpacePoints | [{"example": "source"}, {"example": "special"}] |
      | tag                          | "removed"                                       |

  # UpdateRootNodeAggregateDimensions conflict prevention
  Scenario: Garbage collection will ignore a soft removal if the (root) node has an unpublished dimension update
    Given I change the content dimensions in content repository "default" to:
      | Identifier | Values                                    | Generalizations                                             |
      | example    | general, source, special, peer, otherPeer | special->source->general, peer->general, otherPeer->general |
    When the command TagSubtree is executed with payload:
      | Key                          | Value                    |
      | workspaceName                | "live"                   |
      | nodeAggregateId              | "lady-eleonode-rootford" |
      | coveredDimensionSpacePoint   | {"example": "source"}    |
      | nodeVariantSelectionStrategy | "allSpecializations"     |
      | tag                          | "removed"                |

    And I am in workspace "user-workspace"
    When the command UpdateRootNodeAggregateDimensions is executed with payload and exceptions are caught:
      | Key             | Value                    |
      | nodeAggregateId | "lady-eleonode-rootford" |
    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |
    And soft removal garbage collection is run for content repository default

    Then I expect exactly 7 events to be published on stream "ContentStream:cs-identifier"
    And event at index 6 is of type "SubtreeWasTagged" with payload:
      | Key                          | Expected                                        |
      | workspaceName                | "live"                                          |
      | contentStreamId              | "cs-identifier"                                 |
      | nodeAggregateId              | "lady-eleonode-rootford"                        |
      | affectedDimensionSpacePoints | [{"example": "source"}, {"example": "special"}] |
      | tag                          | "removed"                                       |
