<?php

namespace VoteBundle\Listener;

use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Exception;
use JMS\DiExtraBundle\Annotation as DI;

use VoteBundle\Exception\BadRequestException;
use VoteBundle\Exception\ErrorRedirectException;

class CoreExceptionListener {

    private $logger;
    private $router;
    private $twig;
    private $session;

    /**
     * Constructor
     */
    public function __construct(Logger $logger, Router $router, \Twig_Environment $twig, $session) {
        $this->logger = $logger;
        $this->router = $router;
        $this->twig = $twig;
        $this->session = $session;
    }

    public function onKernelException(GetResponseForExceptionEvent $event) {

        // You get the exception object from the received event
        $exception = $event->getException();

        $this->logger->error("CORE EXCEPTION LISTENER - " . get_class($exception));

        // If it's not one of the exceptions we care about, rethrow
        if ($exception instanceof BadRequestException) {
            $event->setResponse($this->getBadRequestResponse($exception));
        } elseif ($exception instanceof ErrorRedirectException) {
            $event->setResponse($this->getErrorRedirectResponse($exception));
        } else {
            $this->logger->error($exception->getMessage() . " => " . $exception->getFile() . ", line " . $exception->getLine());
            throw $exception;
        }
    }

    /**
     * Returns JSON with error message contained in "result" parameter
     *
     * @param BadRequestException $exception
     * @return JsonResponse
     * @throws Exception
     */
    private function getBadRequestResponse(BadRequestException $exception) {
        $message = array(
            'result' => $exception->getMessage()
            ,'status' => $exception->getStatusCode()
            ,'data' => $exception->getExtraParams()
        );

        $this->logger->error("BAD REQUEST EXCEPTION: " . var_export($message,true));

        // Customize your response object to display the exception details
        $response = new JsonResponse();
        $response->setData($message);
        $response->setStatusCode($exception->getStatusCode());

        return $response;
    }

    /**
     * Sends the user to the named route if there was an error
     *
     * @param ErrorRedirectException $exception
     * @return RedirectResponse
     */
    private function getErrorRedirectResponse(ErrorRedirectException $exception) {

        $message = $exception->getMessage();

        $this->logger->error("ERROR REDIRECT EXCEPTION: " . var_export($message, true));

        $response = new RedirectResponse($this->router->generate($exception->getNamedRoute(), $exception->getURLParams()));

        $this->session->getFlashBag()->set($exception->getVariableName(), $message);

        return $response;
    }
}