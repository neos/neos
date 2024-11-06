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

    private bool $flowSecurityEnabled = false;
    private bool $contentRepositorySecurityEnabled = false;

    private ?TestingProvider $testingProvider = null;

    private ?ActionRequest $mockActionRequest = null;

    private static ?string $testingPolicyPathAndFilename = null;

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
        $this->contentRepositorySecurityEnabled = false;
    }

    #[BeforeFeature]
    #[AfterFeature]
    public static function resetPolicies(): void
    {
        if (self::$testingPolicyPathAndFilename !== null && file_exists(self::$testingPolicyPathAndFilename)) {
            unlink(self::$testingPolicyPathAndFilename);
        }
    }

    private function enableFlowSecurity(): void
    {
        if ($this->flowSecurityEnabled === true) {
            return;
        }
        $this->getObject(PrivilegeManagerInterface::class)->reset();

        $tokenAndProviderFactory = $this->getObject(TokenAndProviderFactoryInterface::class);

        $this->testingProvider = $tokenAndProviderFactory->getProviders()['TestingProvider'];

        $securityContext = $this->getObject(SecurityContext::class);
        $securityContext->clearContext();
        $httpRequest = $this->getObject(ServerRequestFactoryInterface::class)->createServerRequest('GET', 'http://localhost/');
        $this->mockActionRequest = ActionRequest::fromHttpRequest($httpRequest);
        $securityContext->setRequest($this->mockActionRequest);
        $this->flowSecurityEnabled = true;
    }

    private function enableContentRepositorySecurity(): void
    {
        if ($this->contentRepositorySecurityEnabled === true) {
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
        $this->contentRepositorySecurityEnabled = true;
    }

    private function authenticateAccount(Account $account): void
    {
        $this->enableFlowSecurity();
        $this->testingProvider->setAuthenticationStatus(TokenInterface::AUTHENTICATION_SUCCESSFUL);
        $this->testingProvider->setAccount($account);

        $securityContext = $this->getObject(SecurityContext::class);
        $securityContext->clearContext();
        $securityContext->setRequest($this->mockActionRequest);
        $this->getObject(AuthenticationProviderManager::class)->authenticate();
    }

    /**
     * @Given content repository security is enabled
     */
    public function contentRepositorySecurityIsEnabled(): void
    {
        $this->enableContentRepositorySecurity();
    }


    /**
     * @Given The following additional policies are configured:
     */
    public function theFollowingAdditionalPoliciesAreConfigured(PyStringNode $policies): void
    {
        $policyService = $this->getObject(PolicyService::class);
        $policyService->getRoles(); // force initialization
        $policyConfiguration = ObjectAccess::getProperty($policyService, 'policyConfiguration', true);
        $mergedPolicyConfiguration = Arrays::arrayMergeRecursiveOverrule($policyConfiguration, Yaml::parse($policies->getRaw()));

        self::$testingPolicyPathAndFilename = $this->getObject(Environment::class)->getPathToTemporaryDirectory() . 'Policy.yaml';
        file_put_contents(self::$testingPolicyPathAndFilename, Yaml::dump($mergedPolicyConfiguration));

        ObjectAccess::setProperty($policyService, 'initialized', false, true);
        $this->getObject(ConfigurationManager::class)->flushConfigurationCache();
    }

    /**
     * @When the user :username accesses the content graph for workspace :workspaceName
     */
    public function theUserAccessesTheContentGraphForWorkspace(string $username, string $workspaceName): void
    {
        $this->enableContentRepositorySecurity();
        $user = $this->getObject(UserService::class)->getUser($username);
        $this->authenticateAccount($user->getAccounts()->first());
        $this->tryCatchingExceptions(fn () => $this->currentContentRepository->getContentGraph(WorkspaceName::fromString($workspaceName)));
    }

    /**
     * @Then The user :username should not be able to read node :nodeAggregateId
     */
    public function theUserShouldNotBeAbleToReadNode(string $username, string $nodeAggregateId): void
    {
        $user = $this->getObject(UserService::class)->getUser($username);
        $this->authenticateAccount($user->getAccounts()->first());
        $node = $this->currentContentRepository->getContentSubgraph($this->currentWorkspaceName, $this->currentDimensionSpacePoint)->findNodeById(NodeAggregateId::fromString($nodeAggregateId));
        if ($node !== null) {
            Assert::fail(sprintf('Expected node "%s" to be inaccessible to user "%s" but it was loaded', $nodeAggregateId, $username));
        }
    }

    /**
     * @Then The user :username should be able to read node :nodeAggregateId
     */
    public function theUserShouldBeAbleToReadNode(string $username, string $nodeAggregateId): void
    {
        $user = $this->getObject(UserService::class)->getUser($username);
        $this->authenticateAccount($user->getAccounts()->first());
        $node = $this->currentContentRepository->getContentSubgraph($this->currentWorkspaceName, $this->currentDimensionSpacePoint)->findNodeById(NodeAggregateId::fromString($nodeAggregateId));
        if ($node === null) {
            Assert::fail(sprintf('Expected node "%s" to be accessible to user "%s" but it could not be loaded', $nodeAggregateId, $username));
        }
    }
}
