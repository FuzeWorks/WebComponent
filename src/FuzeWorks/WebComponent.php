<?php
/**
 * FuzeWorks WebComponent.
 *
 * The FuzeWorks PHP FrameWork
 *
 * Copyright (C) 2013-2019 TechFuze
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @author    TechFuze
 * @copyright Copyright (c) 2013 - 2019, TechFuze. (http://techfuze.net)
 * @license   https://opensource.org/licenses/MIT MIT License
 *
 * @link  http://techfuze.net/fuzeworks
 * @since Version 1.2.0
 *
 * @version Version 1.2.0
 */

namespace FuzeWorks;

use FuzeWorks\Event\HaltExecutionEvent;
use FuzeWorks\Event\LayoutDisplayEvent;
use FuzeWorks\Event\LayoutLoadEvent;
use FuzeWorks\Event\RouterCallViewEvent;
use FuzeWorks\Event\RouterLoadViewAndControllerEvent;
use FuzeWorks\Event\RouteWebRequestEvent;
use FuzeWorks\Exception\ConfigException;
use FuzeWorks\Exception\EventException;
use FuzeWorks\Exception\Exception;
use FuzeWorks\Exception\FactoryException;
use FuzeWorks\Exception\HaltException;
use FuzeWorks\Exception\NotFoundException;
use FuzeWorks\Exception\OutputException;
use FuzeWorks\Exception\RouterException;
use FuzeWorks\Exception\SecurityException;
use FuzeWorks\Exception\WebException;

class WebComponent implements iComponent
{
    /**
     * Whether WebComponent is configured to handle a web request
     *
     * @var bool
     */
    public static $willHandleRequest = false;

    public function getName(): string
    {
        return "WebComponent";
    }

    public function getClasses(): array
    {
        return [
            'web' => $this,
            'security' => '\FuzeWorks\Security',
            'input' => '\FuzeWorks\Input',
            'output' => '\FuzeWorks\Output',
            'uri' => '\FuzeWorks\URI',
            'resources' => '\FuzeWorks\Resources'
        ];
    }

    public function onAddComponent(Configurator $configurator)
    {
        // Add dependencies
        $configurator->addComponent(new MVCRComponent());

        // Add fallback config directory
        $configurator->addDirectory(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Config',
            'config',
            Priority::LOWEST
        );

        // If WebComponent will handle a request, add some calls to the configurator
        if (self::$willHandleRequest)
        {
            // Invoke methods to prepare system for HTTP calls
            $configurator->call('logger', 'setLoggerTemplate', null, 'logger_http');
        }
    }

    public function onCreateContainer(Factory $container)
    {
    }

    /**
     * On initializing, Initialize UTF8 first, since it's a dependency for other componentClasses
     */
    public function init()
    {
        // First init UTF8
        UTF8::init();

        // Register some base events
        Events::addListener([$this, 'layoutLoadEventListener'], 'layoutLoadEvent', Priority::NORMAL);
    }

    /**
     * Enable the WebComponent to prepare for handling requests
     */
    public function enableComponent()
    {
        self::$willHandleRequest = true;
    }

    /**
     * Disable the WebComponent so it won't prepare for handling requests
     */
    public function disableComponent()
    {
        self::$willHandleRequest = false;
    }

    /**
     * Handle a Web request.
     *
     * Retrieves URI string, routes this URI using the provided routes,
     * appends output and adds listener to view output on shutdown.
     *
     * @return bool
     * @throws OutputException
     * @throws RouterException
     * @throws WebException
     * @throws EventException
     * @throws FactoryException
     */
    public function routeWebRequest(): bool
    {
        if (!self::$willHandleRequest)
            throw new WebException("Could not route web request. WebComponent is not configured to handle requests");

        try {
            // Set the output to display when shutting down
            Events::addListener(function ($event) {
                /** @var Output $output */
                Logger::logInfo("Parsing output...");
                $output = Factory::getInstance()->output;
                $output->display();
                return $event;
            }, 'coreShutdownEvent', Priority::NORMAL);

            // Intercept output of Layout and redirect it to Output
            Events::addListener(function($event){
                /** @var $event LayoutDisplayEvent */
                /** @var Output $output */
                $output = Factory::getInstance('output');
                $output->appendOutput($event->contents);
                $event->setCancelled(true);
            }, 'layoutDisplayEvent', Priority::NORMAL);

            // Add HTTP method prefix to requests to views
            Events::addListener(function($event){
                /** @var Input $input */
                /** @var RouterLoadViewAndControllerEvent $event */
                $input = Factory::getInstance('input');
                $methods = $event->viewMethods[Priority::NORMAL];
                foreach ($methods as $method)
                    $event->addMethod(strtolower($input->method()) . '_' . $method);
                return $event;
            }, 'routerLoadViewAndControllerEvent', Priority::NORMAL);

            // Create an error 500 page when a haltEvent is fired
            Events::addListener([$this, 'haltEventListener'], 'haltExecutionEvent', Priority::NORMAL);
        } catch (EventException $e) {
            throw new WebException("Could not route web request. coreShutdownEvent threw EventException: '".$e->getMessage()."'");
        }

        // Remove the X-Powered-By header, since it's a security risk
        header_remove("X-Powered-By");

        /** @var Router $router */
        /** @var URI $uri */
        /** @var Input $input */
        /** @var Output $output */
        /** @var Security $security */
        /** @var Resources $resources */
        /** @var Config $config */
        $router = Factory::getInstance('router');
        $uri = Factory::getInstance('uri');
        $input = Factory::getInstance('input');
        $output = Factory::getInstance('output');
        $security = Factory::getInstance('security');
        $resources = Factory::getInstance('resources');
        $config = Factory::getInstance('config');

        // First check if this isn't https and we need to redirect
        $redirect = $config->getConfig('web')->get('redirect_to_https');
        if ($redirect && !$input->isHttps())
        {
            Logger::log("Redirecting http traffic to https...");
            $httpsInputs = $input->server(['HTTPS', 'HTTP_HOST', 'REQUEST_URI']);
            $location = 'https://' . $httpsInputs['HTTP_HOST'] . $httpsInputs['REQUEST_URI'];

            $output->setStatusHeader(301);
            $output->setHeader('Location: ' . $location);
            return true;
        }

        // And start logging the request
        Logger::newLevel("Routing web request...");

        // First check if a cached page is available
        $uriString = $uri->uriString();
        if ($output->getCache($uriString))
            return true;

        // Send webRequestEvent, if no cache is found
        /** @var RouteWebRequestEvent $event */
        $event = Events::fireEvent('routeWebRequestEvent', $uriString);
        if ($event->isCancelled())
            return true;

        // Attempt to load a static resource
        if ($resources->serveResource($uri->segmentArray()))
            return true;

        // First test for Cross Site Request Forgery
        try {
            $security->csrf_verify();
        } catch (SecurityException $exception) {
            // If a SecurityException is thrown, first log it
            Logger::logWarning("SecurityException thrown. Registering listener to verify handler in View");

            // Register a listener
            Events::addListener([$this, 'callViewEventListener'], 'routerCallViewEvent', Priority::HIGHEST, $exception);
        }

        // Attempt to load the requested page
        try {
            $viewOutput = $router->route($event->uriString);
        } catch (NotFoundException $e) {
            Logger::logWarning("Requested page not found. Requesting Error/error404 View");
            $output->setStatusHeader(404);

            // Remove listener so that error pages won't be intercepted
            Events::removeListener([$this, 'callViewEventListener'], 'routerCallViewEvent',Priority::HIGHEST);

            // Request 404 page
            try {
                $viewOutput = $router->route('Error/error404');
            } catch (NotFoundException $e) {
                // If still resulting in an error, do something else
                $viewOutput = 'ERROR 404. Page was not found.';
            } catch (Exception $e) {
                Logger::exceptionHandler($e, false);
                $viewOutput = 'ERROR 404. Page was not found.';
            }
        } catch (HaltException $e) {
            Logger::logWarning("Requested page was denied. Requesting Error/error403 View.");
            $output->setStatusHeader(403);

            // Remove listener so that error pages won't be intercepted
            Events::removeListener([$this, 'callViewEventListener'], 'routerCallViewEvent',Priority::HIGHEST);

            try {
                $viewOutput = $router->route('Error/error403');
            } catch (NotFoundException $e) {
                // If still resulting in an error, do something else
                $viewOutput = 'ERROR 403. Forbidden.';
            } catch (Exception $e) {
                Logger::exceptionHandler($e, false);
                $viewOutput = 'ERROR 403. Forbidden.';
            }
        }

        // Append the output
        if (!empty($viewOutput))
            $output->appendOutput($viewOutput);

        Logger::stopLevel();
        return true;
    }

    /**
     * Listener for routerCallViewEvent
     *
     * Fired when a SecurityException is thrown. Verifies if a securityExceptionHandler() method exists.
     * If not, the calling of the view is cancelled. If yes, the calling of the view depends on the
     * result of the method
     *
     * @param RouterCallViewEvent $event
     * @param SecurityException $exception
     */
    public function callViewEventListener(RouterCallViewEvent $event, SecurityException $exception)
    {
        /** @var RouterCallViewEvent $event */
        // If the securityExceptionHandler method exists, cancel based on that methods output
        if (method_exists($event->view, 'securityExceptionHandler'))
            $event->setCancelled(!$event->view->securityExceptionHandler($exception));

        // If not, cancel it immediately
        else
            $event->setCancelled(true);
    }

    /**
     * Listener for haltExecutionEvent
     *
     * Fired when FuzeWorks halts it's execution. Loads an error 500 page.
     *
     * @param $event
     * @throws EventException
     * @throws FactoryException
     * @TODO remove FuzeWorks\Layout dependency
     */
    public function haltEventListener(HaltExecutionEvent $event)
    {
        // Dependencies
        /** @var Output $output */
        /** @var Router $router */
        /** @var Event $event */
        /** @var Layout $layout */
        $output = Factory::getInstance()->output;
        $router = Factory::getInstance()->router;
        $layout = Factory::getInstance()->layouts;

        // Cancel event
        $event->setCancelled(true);

        // Reset the layout engine
        $layout->reset();

        // Remove listener so that error pages won't be intercepted
        Events::removeListener([$this, 'callViewEventListener'], 'routerCallViewEvent',Priority::HIGHEST);

        try {
            // And handle consequences
            Logger::logError("Execution halted. Providing error 500 page.");
            $output->setStatusHeader(500);
            $viewOutput = $router->route('Error/error500');
        } catch (Exception $error500Exception) {
            Logger::exceptionHandler($error500Exception, false);
            $viewOutput = 'ERROR 500. Page could not be loaded.';
        }

        // Finally append output and shutdown
        $output->appendOutput($viewOutput);
    }

    /**
     * Listener for layoutLoadEvent
     *
     * Assigns variables from the WebComponent to Layout engines.
     *
     * @param LayoutLoadEvent $event
     * @throws ConfigException
     * @throws FactoryException
     */
    public function layoutLoadEventListener($event)
    {
        // Dependencies
        /** @var Security $security */
        /** @var Config $config */
        $security = Factory::getInstance()->security;
        $config = Factory::getInstance()->config;

        /** @var LayoutLoadEvent $event */
        $event->assign('csrfHash', $security->get_csrf_hash());
        $event->assign('csrfTokenName', $security->get_csrf_token_name());
        $event->assign('siteURL', $config->getConfig('web')->get('base_url'));
        $event->assign('serverName', $config->getConfig('web')->get('serverName'));

        Logger::logInfo("Assigned variables to TemplateEngine from WebComponent");
    }
}