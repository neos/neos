@contentrepository @adapters=DoctrineDBAL
Feature: Copy nodes (without dimensions)

  Background:
    Given using no content dimensions
    And using the following node types:
    """yaml
    'Neos.ContentRepository.Testing:Document':
      properties:
        title:
          type: string
        array:
          type: array
        uri:
          type: GuzzleHttp\Psr7\Uri
        date:
          type: DateTimeImmutable
      references:
        ref: []
    """
    And using identifier "default", I define a content repository
    And I am in content repository "default"
    And the command CreateRootWorkspace is executed with payload:
      | Key                | Value           |
      | workspaceName      | "live"          |
      | newContentStreamId | "cs-identifier" |
    And I am in workspace "live"
    And the command CreateRootNodeAggregateWithNode is executed with payload:
      | Key             | Value                         |
      | nodeAggregateId | "lady-eleonode-rootford"      |
      | nodeTypeName    | "Neos.ContentRepository:Root" |

    When the following CreateNodeAggregateWithNode commands are executed:
      | nodeAggregateId            | parentNodeAggregateId  | nodeTypeName                            |
      | sir-david-nodenborough     | lady-eleonode-rootford | Neos.ContentRepository.Testing:Document |
      | nody-mc-nodeface           | sir-david-nodenborough | Neos.ContentRepository.Testing:Document |
      | sir-nodeward-nodington-iii | lady-eleonode-rootford | Neos.ContentRepository.Testing:Document |

  Scenario: Simple singular node aggregate is copied
    When I am in workspace "live" and dimension space point {}
    When copy nodes recursively is executed with payload:
      | Key                                    | Value                                                             |
      | sourceDimensionSpacePoint              | {}                                                                |
      | sourceNodeAggregateId                  | "sir-nodeward-nodington-iii"                                      |
      | targetDimensionSpacePoint              | {}                                                                |
      | targetParentNodeAggregateId            | "nody-mc-nodeface"                                                |
      | targetNodeName                         | "target-nn"                                                       |
      | targetSucceedingSiblingnodeAggregateId | null                                                              |
      | nodeAggregateIdMapping                 | {"sir-nodeward-nodington-iii": "sir-nodeward-nodington-iii-copy"} |

    Then I expect node aggregate identifier "sir-nodeward-nodington-iii-copy" to lead to node cs-identifier;sir-nodeward-nodington-iii-copy;{}
    And I expect the node aggregate "sir-nodeward-nodington-iii-copy" to exist
    And I expect this node aggregate to be classified as "regular"
    And I expect this node aggregate to be named "target-nn"
    And I expect this node aggregate to be of type "Neos.ContentRepository.Testing:Document"
    And I expect this node aggregate to occupy dimension space points [[]]
    And I expect this node aggregate to disable dimension space points []
    And I expect this node aggregate to have no child node aggregates
    And I expect this node aggregate to have the parent node aggregates ["nody-mc-nodeface"]

  Scenario: Singular node aggregate is copied with (complex) properties
    When I am in workspace "live" and dimension space point {}
    And the command SetNodeProperties is executed with payload:
      | Key             | Value                                                                                                                                                                            |
      | nodeAggregateId | "sir-nodeward-nodington-iii"                                                                                                                                                     |
      | propertyValues  | {"title": "Original Text", "array": {"givenName":"Nody", "familyName":"McNodeface"}, "uri": {"__type": "GuzzleHttp\\Psr7\\Uri", "value": "https://neos.de"}, "date": {"__type": "DateTimeImmutable", "value": "2001-09-22T12:00:00+00:00"}} |

    When copy nodes recursively is executed with payload:
      | Key                                    | Value                                                             |
      | sourceDimensionSpacePoint              | {}                                                                |
      | sourceNodeAggregateId                  | "sir-nodeward-nodington-iii"                                      |
      | targetDimensionSpacePoint              | {}                                                                |
      | targetParentNodeAggregateId            | "nody-mc-nodeface"                                                |
      | targetNodeName                         | "target-nn"                                                       |
      | targetSucceedingSiblingnodeAggregateId | null                                                              |
      | nodeAggregateIdMapping                 | {"sir-nodeward-nodington-iii": "sir-nodeward-nodington-iii-copy"} |

    And I expect node aggregate identifier "sir-nodeward-nodington-iii-copy" to lead to node cs-identifier;sir-nodeward-nodington-iii-copy;{}
    And I expect this node to have the following serialized properties:
      | Key   | Type                | Value                                          |
      | title | string              | "Original Text"                                |
      | array | array               | {"givenName":"Nody","familyName":"McNodeface"} |
      | date  | DateTimeImmutable   | "2001-09-22T12:00:00+00:00"                    |
      | uri   | GuzzleHttp\Psr7\Uri | "https://neos.de"                              |

  Scenario: Singular node aggregate is copied with references
    When I am in workspace "live" and dimension space point {}
    And the command SetNodeReferences is executed with payload:
      | Key                   | Value                                                                            |
      | sourceNodeAggregateId | "sir-nodeward-nodington-iii"                                                     |
      | references            | [{"referenceName": "ref", "references": [{"target": "sir-david-nodenborough"}]}] |

    When copy nodes recursively is executed with payload:
      | Key                                    | Value                                                             |
      | sourceDimensionSpacePoint              | {}                                                                |
      | sourceNodeAggregateId                  | "sir-nodeward-nodington-iii"                                      |
      | targetDimensionSpacePoint              | {}                                                                |
      | targetParentNodeAggregateId            | "nody-mc-nodeface"                                                |
      | targetNodeName                         | "target-nn"                                                       |
      | targetSucceedingSiblingnodeAggregateId | null                                                              |
      | nodeAggregateIdMapping                 | {"sir-nodeward-nodington-iii": "sir-nodeward-nodington-iii-copy"} |

    And I expect node aggregate identifier "sir-nodeward-nodington-iii-copy" to lead to node cs-identifier;sir-nodeward-nodington-iii-copy;{}
    And I expect this node to have the following references:
      | Name | Node                                    | Properties |
      | ref  | cs-identifier;sir-david-nodenborough;{} | null       |
