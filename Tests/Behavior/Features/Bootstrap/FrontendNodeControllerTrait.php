<?php

declare(strict_types=1);

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Behat\Gherkin\Node\PyStringNode;
use GuzzleHttp\Psr7\Message;
use Neos\ContentRepository\TestSuite\Behavior\Features\Bootstrap\CRTestSuiteRuntimeVariables;
use Neos\Fusion\Core\Cache\ContentCache;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Assert;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;

/**
 * @internal only for behat tests within the Neos.Neos package
 */
trait FrontendNodeControllerTrait
{
    use CRTestSuiteRuntimeVariables;

    private ResponseInterface|null $frontendNodeControllerResponse = null;

    /**
     * @template T of object
     * @param class-string<T> $className
     *
     * @return T
     */
    abstract private function getObject(string $className): object;

    /**
     * @BeforeScenario
     */
    public function setupFrontendNodeControllerTrait(): void
    {
        $this->getObject(ContentCache::class)->flush();
        $this->getObject(\Neos\Neos\Testing\TestingFusionAutoIncludeHandler::class)->reset();
        $this->frontendNodeControllerResponse = null;
    }

    /**
     * @When I have the following Fusion file :fileName:
     */
    public function iHaveTheFollowingFusionFile(PyStringNode $fusionCode, string $fileName)
    {
        if (!str_starts_with($fileName, 'vfs://fusion/')) {
            throw new \InvalidArgumentException('Fusion file name must be virtual.');
        }
        vfsStream::setup('fusion');
        file_put_contents($fileName, $fusionCode->getRaw());
    }

    /**
     * @When the Fusion code for package :package is:
     */
    public function iHaveTheFollowingFusionCodeForTheSite(PyStringNode $fusionCode, string $package)
    {
        $testingFusionHandler = $this->getObject(\Neos\Neos\Testing\TestingFusionAutoIncludeHandler::class);
        $testingFusionHandler->setFusionForPackage($package, \Neos\Fusion\Core\FusionSourceCodeCollection::fromString($fusionCode->getRaw()));
    }

    /**
     * @When I declare the following controller :fullyQualifiedClassName:
     */
    public function iDeclareTheFollowingController(string $fullyQualifiedClassName, PyStringNode $expectedResult): void
    {
        eval(
            /** make the controller implement our hacked {@see BehatRuntimeActionController} instead and remove the php tag */
            str_replace(
                ['extends ActionController', '<?php'],
                ['extends \\BehatRuntimeActionController', ''],
                $expectedResult->getRaw()
            )
        );

        /** @var class-string<BehatRuntimeActionController> $controllerClassName */
        $controllerClassName = '\\' . ltrim($fullyQualifiedClassName, '\\');

        $controllerClassName::registerInstance();
    }

    /**
     * @When I dispatch the following request :requestUri
     */
    public function iDispatchTheFollowingRequest(string $requestUri)
    {
        $testingFusionHandler = $this->getObject(\Neos\Neos\Testing\TestingFusionAutoIncludeHandler::class);
        $testingFusionHandler->setIncludeFusionPackage('Neos.Fusion');
        $testingFusionHandler->setIncludeFusionPackage('Neos.Neos');

        $httpRequest = $this->getObject(ServerRequestFactoryInterface::class)->createServerRequest('GET', $requestUri);

        $this->frontendNodeControllerResponse = $this->getObject(\Neos\Flow\Http\Middleware\MiddlewaresChain::class)->handle(
            $httpRequest
        );
    }

    /**
     * @Then I expect the following response:
     */
    public function iExpectTheFollowingResponse(PyStringNode $expectedResult): void
    {
        Assert::assertNotNull($this->frontendNodeControllerResponse);
        Assert::assertEquals($expectedResult->getRaw(), str_replace("\r\n", "\n", Message::toString($this->frontendNodeControllerResponse->withoutHeader('Content-Length'))));
    }
}
