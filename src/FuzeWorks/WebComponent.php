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

use FuzeWorks\Exception\EventException;
use FuzeWorks\Exception\Exception;
use FuzeWorks\Exception\NotFoundException;
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
        ];
    }

    /**
     * @param Configurator $configurator
     * @todo WebComponent will not always be running when added to FuzeWorks, move this into a separate method
     */
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

    public function init()
    {
        // First init UTF8
        UTF8::init();
    }

    public function enableComponent()
    {
        self::$willHandleRequest = true;
    }

    public function disableComponent()
    {
        self::$willHandleRequest = false;
    }

    /**
     * @throws WebException
     */
    public function routeWebRequest(): bool
    {
        if (!self::$willHandleRequest)
            throw new WebException("Could not route web request. WebComponent is not configured to handle requests");

        // Set the output to display when shutting down
        try {
            Events::addListener(function () {
                /** @var Output $output */
                Logger::logInfo("Parsing output...");
                $output = Factory::getInstance()->output;
                $output->display();
            }, 'coreShutdownEvent', Priority::NORMAL);
        } catch (EventException $e) {
            throw new WebException("Could not route web request. coreShutdownEvent threw EventException: '".$e->getMessage()."'");
        }

        /** @var Router $router */
        $router = Factory::getInstance()->router;

        /** @var URI $uriObject */
        $uriObject = Factory::getInstance()->uri;
        $uri = $uriObject->uriString();

        /** @var Output $output */
        $output = Factory::getInstance()->output;

        // Attempt to load the requested page
        try {
            $viewOutput = $router->route($uri);
        } catch (NotFoundException $e) {
            Logger::logWarning("Requested page not found. Requesting Error/error404 View");
            $output->setStatusHeader(404);

            // Request 404 page=
            try {
                $viewOutput = $router->route('Error/error404');
            } catch (NotFoundException $e) {
                // If still resulting in an error, do something else
                $viewOutput = 'ERROR 404. Page was not found.';
            } catch (Exception $e) {
                Logger::exceptionHandler($e, false);
                $viewOutput = 'ERROR 404. Page was not found.';
            }
        } catch (Exception $e) {
            Logger::exceptionHandler($e, false);
            $output->setStatusHeader(500);
            try {
                $viewOutput = $router->route('Error/error500');
            } catch (Exception $error500Exception) {
                Logger::exceptionHandler($error500Exception, false);
                $viewOutput = 'ERROR 500. Page could not be loaded.';
            }
        }

        // Append the output
        if (!empty($viewOutput))
            $output->appendOutput($viewOutput);

        return true;
    }
}