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

  Scenario: Garbage collection always ignores root node aggregates
    Given the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                          |
      | nodeAggregateId | "lady-eleonode-other-rootford" |
      | nodeTypeName    | "Neos.Neos:OtherSites"         |
    When the command TagSubtree is executed with payload:
      | Key                          | Value                    |
      | workspaceName                | "live"                   |
      | nodeAggregateId              | "lady-eleonode-rootford" |
      | coveredDimensionSpacePoint   | {"example": "source"}    |
      | nodeVariantSelectionStrategy | "allSpecializations"     |
      | tag                          | "removed"                |

    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |

    Then I expect the following hard removal conflicts to be impending:
      | nodeAggregateId | dimensionSpacePoints |

    When soft removal garbage collection is run for content repository default
    Then I expect exactly 4 events to be published on stream "ContentStream:cs-identifier"
    And event at index 3 is of type "SubtreeWasTagged" with payload:
      | Key                          | Expected                                        |
      | workspaceName                | "live"                                          |
      | contentStreamId              | "cs-identifier"                                 |
      | nodeAggregateId              | "lady-eleonode-rootford"                        |
      | affectedDimensionSpacePoints | [{"example": "source"}, {"example": "special"}] |
      | tag                          | "removed"                                       |

  Scenario: Garbage collection still ignores root node aggregate with an unpublished dimension update
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

    And I expect the node aggregate "lady-eleonode-rootford" to exist

    When the command UpdateRootNodeAggregateDimensions is executed with payload and exceptions are caught:
      | Key             | Value                    |
      | nodeAggregateId | "lady-eleonode-rootford" |
      | workspaceName   | "user-workspace"         |
    And the command RebaseWorkspace is executed with payload:
      | Key           | Value            |
      | workspaceName | "user-workspace" |

    Then I expect the following hard removal conflicts to be impending:
      | nodeAggregateId | dimensionSpacePoints |

    When soft removal garbage collection is run for content repository default
    Then I expect exactly 3 events to be published on stream "ContentStream:cs-identifier"
    And event at index 2 is of type "SubtreeWasTagged" with payload:
      | Key                          | Expected                                        |
      | workspaceName                | "live"                                          |
      | contentStreamId              | "cs-identifier"                                 |
      | nodeAggregateId              | "lady-eleonode-rootford"                        |
      | affectedDimensionSpacePoints | [{"example": "source"}, {"example": "special"}] |
      | tag                          | "removed"                                       |
