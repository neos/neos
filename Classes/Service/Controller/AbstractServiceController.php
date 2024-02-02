<?php
namespace Neos\Neos\Service\Controller;

/*
 * This file is part of the Neos.Neos package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Exception as FlowException;
use Neos\Flow\Log\ThrowableStorageInterface;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Mvc\ActionRequest;
use Neos\Flow\Mvc\ActionResponse;
use Neos\Flow\Mvc\Controller\ActionController;
use Neos\Flow\Mvc\Exception\StopActionException;
use Neos\Neos\Controller\BackendUserTranslationTrait;

/**
 * Abstract Service Controller
 */
abstract class AbstractServiceController extends ActionController
{
    use BackendUserTranslationTrait;

    /**
     * @var array
     */
    protected $supportedMediaTypes = ['application/json'];

    /**
     * Cant be named here $throwableStorage see https://github.com/neos/neos-development-collection/issues/3858
     *
     * @Flow\Inject
     * @var ThrowableStorageInterface
     */
    protected $throwableStorage2;

    /**
     * A preliminary error action for handling validation errors
     *
     * @return void
     * @throws StopActionException
     */
    public function errorAction()
    {
        if ($this->arguments->getValidationResults()->hasErrors()) {
            $errors = [];
            foreach ($this->arguments->getValidationResults()->getFlattenedErrors() as $propertyName => $propertyErrors) {
                foreach ($propertyErrors as $propertyError) {
                    /** @var \Neos\Error\Messages\Error $propertyError */
                    $error = [
                        'severity' => $propertyError->getSeverity(),
                        'message' => $propertyError->render()
                    ];
                    if ($propertyError->getCode()) {
                        $error['code'] = $propertyError->getCode();
                    }
                    if ($propertyError->getTitle()) {
                        $error['title'] = $propertyError->getTitle();
                    }
                    $errors[$propertyName][] = $error;
                }
            }
            $this->throwStatus(409, null, json_encode($errors));
        }
        $this->throwStatus(400);
    }

    /**
     * Catch exceptions while processing an exception and respond to JSON format
     * TODO: This is an explicit exception handling that will be replaced by format-enabled exception handlers.
     *
     * @param ActionRequest $request The request object
     * @param ActionResponse $response The response, modified by this handler
     * @return void
     * @throws StopActionException
     * @throws \Exception
     */
    public function processRequest(ActionRequest $request, ActionResponse $response)
    {
        try {
            parent::processRequest($request, $response);
        } catch (StopActionException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            if ($this->request->getFormat() !== 'json' || !$response instanceof ActionResponse) {
                throw $exception;
            }
            $exceptionData = $this->convertException($exception);
            $response->setContentType('application/json');
            if ($exception instanceof FlowException) {
                $response->setStatusCode($exception->getStatusCode());
            } else {
                $response->setStatusCode(500);
            }
            $response->setContent(json_encode(['error' => $exceptionData]));
            $this->logger->error($this->throwableStorage2->logThrowable($exception), LogEnvironment::fromMethodName(__METHOD__));
        }
    }

    /**
     * @param \Exception $exception
     * @return array
     */
    protected function convertException(\Exception $exception)
    {
        $exceptionData = [];
        if ($this->objectManager->getContext()->isProduction()) {
            if ($exception instanceof FlowException) {
                $exceptionData['message'] = 'When contacting the maintainer of this application please mention the following reference code:<br /><br />' . $exception->getReferenceCode();
            }
        } else {
            $exceptionData = [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
            ];
            $splitMessagePattern = '/
                (?<=                # Begin positive lookbehind.
                  [.!?]\s           # Either an end of sentence punct,
                | \n                # or line break
                )
                (?<!                # Begin negative lookbehind.
                  i\.E\.\s          # Skip "i.E."
                )                   # End negative lookbehind.
                /ix';
            $sentences = preg_split($splitMessagePattern, $exception->getMessage(), 2, PREG_SPLIT_NO_EMPTY);
            if (!isset($sentences[1])) {
                $exceptionData['message'] = $exception->getMessage();
            } else {
                $exceptionData['message'] = trim($sentences[0]);
                $exceptionData['details'] = trim($sentences[1]);
            }
            if ($exception instanceof FlowException) {
                $exceptionData['referenceCode'] = $exception->getReferenceCode();
            }
            if ($exception->getPrevious() !== null) {
                $exceptionData['previous'] = $this->convertException($exception->getPrevious());
            }
        }
        return $exceptionData;
    }
}
