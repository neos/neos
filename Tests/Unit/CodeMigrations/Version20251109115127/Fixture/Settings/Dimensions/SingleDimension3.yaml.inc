# some YAML with comments
Neos:
  ContentRepository:
    contentDimensions:
      language:
        label: 'Neos.Demo:Main:contentDimensions.language'
        icon: icon-language
        default: en_US
        defaultPreset: en_US
        presets:
          en_US:
            label: 'English (US)'
            uriSegment: en
            # no values here, should produce error

-----
Neos:
  ContentRepositoryRegistry:
    contentRepositories:
      default:
        contentDimensions:
          # TODO: FIXME: For preset "en_US", did not find any dimension values underneath "values"
          language:
            label: 'Neos.Demo:Main:contentDimensions.language'
            icon: icon-language
  Neos:
    sites:
      '*':
        contentDimensions:
          # defaultDimensionSpacePoint is used for the homepage (URL /)
          defaultDimensionSpacePoint:
            language: en_US
          resolver:
            factoryClassName: Neos\Neos\FrontendRouting\DimensionResolution\Resolver\UriPathResolverFactory
            options:
              segments:
                -
                  dimensionIdentifier: language
                  # dimensionValue => uriPathSegment (empty uriPathSegment allowed)
                  dimensionValueMapping: {  }
