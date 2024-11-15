<?php

declare(strict_types=1);

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Behat\Gherkin\Node\PyStringNode;
use Behat\Hook\AfterFeature;
use Behat\Hook\BeforeFeature;
use Behat\Hook\BeforeScenario;
use Neos\ContentRepository\BehavioralTests\TestSuite\Behavior\CRBehavioralTestsSubjectProvider;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceFactoryDependencies;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceFactoryInterface;
use Neos\ContentRepository\Core\Factory\ContentRepositoryServiceInterface;
use Neos\ContentRepository\Core\Projection\ContentGraph\ContentGraphProjectionInterface;
use Neos\ContentRepository\Core\SharedModel\Node\NodeAggregateId;
use Neos\ContentRepository\Core\SharedModel\Workspace\WorkspaceName;
use Neos\ContentRepository\TestSuite\Behavior\Features\Bootstrap\Helpers\TestingAuthProvider;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Security\Account;
use Neos\Flow\Security\Authentication\AuthenticationProviderManager;
use Neos\Flow\Security\Authentication\Provider\TestingProvider;
use Neos\Flow\Security\Authentication\TokenAndProviderFactoryInterface;
use Neos\Flow\Security\Authentication\TokenInterface;
use Neos\Flow\Security\Authorization\PrivilegeManagerInterface;
use Neos\Flow\Security\Context as SecurityContext;
use Neos\Flow\Security\Policy\PolicyService;
use Neos\Flow\Utility\Environment;
use Neos\Neos\Domain\Service\UserService;
use Neos\Neos\Security\Authorization\Privilege\ReadNodePrivilege;
use Neos\Neos\Security\ContentRepositoryAuthProvider\ContentRepositoryAuthProviderFactory;
use Neos\Utility\Arrays;
use Neos\Utility\ObjectAccess;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Step implementations and helper for Content Repository Security related tests inside Neos.Neos
 *
 * @internal only for behat tests within the Neos.Neos package
 */
trait ContentRepositorySecurityTrait
{
    use CRBehavioralTestsSubjectProvider;
    use ExceptionsTrait;

    private bool $crSecurity_flowSecurityEnabled = false;
    private bool $crSecurity_contentRepositorySecurityEnabled = false;

    private ?TestingProvider $crSecurity_testingProvider = null;

    private ?ActionRequest $crSecurity_mockActionRequest = null;

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    abstract private function getObject(string $className): object;

    #[BeforeScenario]
    public function resetContentRepositorySecurity(): void
    {
        TestingAuthProvider::resetAuthProvider();
        $this->crSecurity_contentRepositorySecurityEnabled = false;
        $this->crSecurity_flowSecurityEnabled = false;

        $policyService = $this->getObject(PolicyService::class);
        // reset the $policyConfiguration to the default (fetched from the original ConfigurationManager)
        $this->getObject(PolicyService::class)->reset(); // TODO also reset privilegeTargets in ->reset()
        ObjectAccess::setProperty($policyService, 'privilegeTargets', [], true);
        $policyService->injectConfigurationManager($this->getObject(ConfigurationManager::class));

    }

    private function enableFlowSecurity(): void
    {
        if ($this->crSecurity_flowSecurityEnabled === true) {
            return;
        }
        $this->getObject(PrivilegeManagerInterface::class)->reset();

        $tokenAndProviderFactory = $this->getObject(TokenAndProviderFactoryInterface::class);

        $this->crSecurity_testingProvider = $tokenAndProviderFactory->getProviders()['TestingProvider'];

        $securityContext = $this->getObject(SecurityContext::class);
        $securityContext->clearContext();
        $httpRequest = $this->getObject(ServerRequestFactoryInterface::class)->createServerRequest('GET', 'http://localhost/');
        $this->crSecurity_mockActionRequest = ActionRequest::fromHttpRequest($httpRequest);
        $securityContext->setRequest($this->crSecurity_mockActionRequest);
        $this->crSecurity_flowSecurityEnabled = true;
    }

    private function enableContentRepositorySecurity(): void
    {
        if ($this->crSecurity_contentRepositorySecurityEnabled === true) {
            return;
        }
        $contentRepositoryAuthProviderFactory = $this->getObject(ContentRepositoryAuthProviderFactory::class);
        $contentGraphProjection = $this->getContentRepositoryService(new class implements ContentRepositoryServiceFactoryInterface {
            public function build(ContentRepositoryServiceFactoryDependencies $serviceFactoryDependencies): ContentRepositoryServiceInterface
            {
                $contentGraphProjection = $serviceFactoryDependencies->projectionsAndCatchUpHooks->contentGraphProjection;
                return new class ($contentGraphProjection) implements ContentRepositoryServiceInterface {
                    public function __construct(
                        public ContentGraphProjectionInterface $contentGraphProjection,
                    ) {
                    }
                };
            }
        })->contentGraphProjection;
        $contentRepositoryAuthProvider = $contentRepositoryAuthProviderFactory->build($this->currentContentRepository->id, $contentGraphProjection->getState());

        TestingAuthProvider::replaceAuthProvider($contentRepositoryAuthProvider);
        $this->crSecurity_contentRepositorySecurityEnabled = true;
    }

    private function authenticateAccount(Account $account): void
    {
        $this->enableFlowSecurity();
        $this->crSecurity_testingProvider->setAuthenticationStatus(TokenInterface::AUTHENTICATION_SUCCESSFUL);
        $this->crSecurity_testingProvider->setAccount($account);

        $securityContext = $this->getObject(SecurityContext::class);
        $securityContext->clearContext();
        $securityContext->setRequest($this->crSecurity_mockActionRequest);
        $this->getObject(AuthenticationProviderManager::class)->authenticate();
    }

    /**
     * @Given content repository security is enabled
     */
    public function contentRepositorySecurityIsEnabled(): void
    {
        $this->enableFlowSecurity();
        $this->enableContentRepositorySecurity();
    }


    /**
     * @Given The following additional policies are configured:
     */
    public function theFollowingAdditionalPoliciesAreConfigured(PyStringNode $policies): void
    {
        $policyService = $this->getObject(PolicyService::class);

        $mergedPolicyConfiguration = Arrays::arrayMergeRecursiveOverrule(
            $this->getObject(ConfigurationManager::class)->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_POLICY),
            Yaml::parse($policies->getRaw())
        );

        // if we de-initialise the PolicyService and set a new $policyConfiguration (by injecting a stub ConfigurationManager which will be used)
        // we can change the roles and privileges at runtime :D
        $policyService->reset(); // TODO also reset privilegeTargets in ->reset()
        ObjectAccess::setProperty($policyService, 'privilegeTargets', [], true);
        $policyService->injectConfigurationManager(new class ($mergedPolicyConfiguration) extends ConfigurationManager
        {
            public function __construct(
                private array $mergedPolicyConfiguration
            ) {
            }

            public function getConfiguration(string $configurationType, string $configurationPath = null)
            {
                Assert::assertSame(ConfigurationManager::CONFIGURATION_TYPE_POLICY, $configurationType);
                Assert::assertSame(null, $configurationPath);
                return $this->mergedPolicyConfiguration;
            }
        });
    }

    /**
     * @When I am authenticated as :username
     */
    public function iAmAuthenticatedAs(string $username): void
    {
        $user = $this->getObject(UserService::class)->getUser($username);
        $this->authenticateAccount($user->getAccounts()->first());
    }


    /**
     * @When I access the content graph for workspace :workspaceName
     */
    public function iAccessesTheContentGraphForWorkspace(string $workspaceName): void
    {
        $this->tryCatchingExceptions(fn () => $this->currentContentRepository->getContentGraph(WorkspaceName::fromString($workspaceName)));
    }

    /**
     * @Then I should not be able to read node :nodeAggregateId
     */
    public function iShouldNotBeAbleToReadNode(string $nodeAggregateId): void
    {
        $node = $this->currentContentRepository->getContentSubgraph($this->currentWorkspaceName, $this->currentDimensionSpacePoint)->findNodeById(NodeAggregateId::fromString($nodeAggregateId));
        if ($node !== null) {
            Assert::fail(sprintf('Expected node "%s" to be inaccessible but it was loaded', $nodeAggregateId));
        }
    }

    /**
     * @Then I should be able to read node :nodeAggregateId
     */
    public function iShouldBeAbleToReadNode(string $nodeAggregateId): void
    {
        $node = $this->currentContentRepository->getContentSubgraph($this->currentWorkspaceName, $this->currentDimensionSpacePoint)->findNodeById(NodeAggregateId::fromString($nodeAggregateId));
        if ($node === null) {
            Assert::fail(sprintf('Expected node "%s" to be accessible but it could not be loaded', $nodeAggregateId));
        }
    }
}
