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
    'Neos.Neos:OtherSites':
      superTypes:
        'Neos.ContentRepository:Root': true
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
    And the command CreateWorkspace is executed with payload:
      | Key                | Value            |
      | workspaceName      | "user-workspace" |
      | baseWorkspaceName  | "live"           |
      | newContentStreamId | "user-cs-id"     |

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
    Then I expect the following hard removal conflicts to be impending:
      | nodeAggregateId        | dimensionSpacePoints                            |
      | lady-eleonode-rootford | [{"example": "source"}, {"example": "special"}] |

    When soft removal garbage collection is run for content repository default
    Then I expect exactly 3 events to be published on stream "ContentStream:cs-identifier"
    And event at index 2 is of type "SubtreeWasTagged" with payload:
      | Key                          | Expected                                        |
      | workspaceName                | "live"                                          |
      | contentStreamId              | "cs-identifier"                                 |
      | nodeAggregateId              | "lady-eleonode-rootford"                        |
      | affectedDimensionSpacePoints | [{"example": "source"}, {"example": "special"}] |
      | tag                          | "removed"                                       |

  Scenario: Garbage collection will transform a soft removal if there ony is an unrelated unpublished dimension update
    Given the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                          |
      | nodeAggregateId | "lady-eleonode-other-rootford" |
      | nodeTypeName    | "Neos.Neos:OtherSites"         |
    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |
    And I change the content dimensions in content repository "default" to:
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
      | Key             | Value                          |
      | nodeAggregateId | "lady-eleonode-other-rootford" |
    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |
    Then I expect the following hard removal conflicts to be impending:
      | nodeAggregateId | dimensionSpacePoints |

    When soft removal garbage collection is run for content repository default
    Then I expect exactly 8 events to be published on stream "ContentStream:cs-identifier"
    And event at index 7 is of type "NodeAggregateWasRemoved" with payload:
      | Key                                  | Expected                                                                                     |
      | workspaceName                        | "live"                                                                                       |
      | contentStreamId                      | "cs-identifier"                                                                              |
      | nodeAggregateId                      | "lady-eleonode-rootford"                                                                     |
      | affectedOccupiedDimensionSpacePoints | []                                                                                           |
      | affectedCoveredDimensionSpacePoints  | [{"example": "source"}, {"example": "special"}] |

    When the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |
    # no exceptions must be thrown
