<?php

namespace Nip\Http\Kernel;

use Exception;
use Nip\Application;
use Nip\Application\ApplicationInterface;
use Nip\Http\Response\Response;
use Nip\Http\Response\ResponseFactory;
use Nip\Http\ServerMiddleware\Dispatcher;
use Nip\Request;
use Nip\Router\Router;
use Nip\Session\Middleware\StartSession;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run as WhoopsRun;

/**
 * Class Kernel
 * @package Nip\Http\Kernel
 */
class Kernel implements KernelInterface
{
    /**
     * The application implementation.
     *
     * @var Application
     */
    protected $app;

    /**
     * The router instance.
     *
     * @var Router
     */
    protected $router;

    /**
     * The application's middleware stack.
     *
     * @var array
     */
    protected $middleware = [
        StartSession::class
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [];

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [];

    /**
     * Create a new HTTP kernel instance.
     *
     * @param  ApplicationInterface $app
     * @param  Router $router
     */
    public function __construct(ApplicationInterface $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;
    }

    /**
     * Handle an incoming HTTP request.
     *
     * @param  SymfonyRequest $request
     * @param int $type
     * @param bool $catch
     * @return ResponseInterface
     */
    public function handle(SymfonyRequest $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        try {
            return $this->handleRaw($request, $type);
        } catch (Exception $e) {
            $this->reportException($e);
            $response = $this->renderException($request, $e);
        } catch (Throwable $e) {
            $this->reportException($e = new FatalThrowableError($e));
            $response = $this->renderException($request, $e);
        }
//        event(new Events\RequestHandled($request, $response));
        return $response;
    }

    /**
     * Handles a request to convert it to a response.
     *
     * @param SymfonyRequest $request A Request instance
     * @param int $type The type of the request
     *
     * @return ResponseInterface A Response instance
     *
     * @throws \LogicException       If one of the listener does not behave as expected
     * @throws NotFoundHttpException When controller cannot be found
     */
    protected function handleRaw(SymfonyRequest $request, $type = self::MASTER_REQUEST)
    {
        return (new Dispatcher($this->middleware))->dispatch($request);
    }

    /**
     * Report the exception to the exception handler.
     *
     * @param  Exception $e
     * @return void
     */
    protected function reportException(Exception $e)
    {
        $this->getLogger()->handleException($e);
    }

    /**
     * @param Request $request
     * @param Exception $e
     * @return ResponseInterface
     */
    protected function renderException($request, Exception $e)
    {
        if ($this->getStaging()->getStage()->isPublic()) {
            $this->getDispatcher()->setErrorController();

            return $this->getResponseFromRequest($request);
        } else {
            $whoops = new WhoopsRun;
            $whoops->allowQuit(false);
            $whoops->writeToOutput(false);
            $whoops->pushHandler(new PrettyPageHandler());

            return ResponseFactory::make($whoops->handleException($e));
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     */
    public function terminate(Request $request, Response $response)
    {
        $this->terminateMiddleware($request, $response);
        $this->getApplication()->terminate();
    }

    /**
     * Call the terminate method on any terminable middleware.
     *
     * @param  Request $request
     * @param  Response $response
     * @return void
     */
    protected function terminateMiddleware($request, $response)
    {
    }

    /**
     * Get the application instance.
     *
     * @return Application
     */
    public function getApplication()
    {
        return $this->app;
    }

    /**
     * Send the given request through the middleware / router.
     *
     * @param  SymfonyRequest $request
     * @return Response
     */
    protected function sendRequestThroughRouter($request)
    {
        $this->app->share('request', $request);

//        Facade::clearResolvedInstance('request');

        $this->preHandleRequest();
        $this->preRouting();

        // check is valid request
        if ($this->isValidRequest($request)) {
            $this->route($request);
        } else {
            die('');
        }

        $this->postRouting();
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function isValidRequest($request)
    {
        if ($request->isMalicious()) {
            return false;
        }

        return true;
    }

    public function postRouting()
    {
    }

    /**
     * @param Exception $e
     * @param Request $request
     * @return Response
     */
    protected function handleException($request, Exception $e)
    {
        $this->reportException($e);

        return $this->renderException($request, $e);
    }
}
