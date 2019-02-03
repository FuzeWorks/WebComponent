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


class WebComponent implements iComponent
{

    public function getName(): string
    {
        return "WebComponent";
    }

    public function getClasses(): array
    {
        return [
            'web' => $this,
            'input' => '\FuzeWorks\Input',
            'output' => '\FuzeWorks\Output'
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

        // Invoke methods to prepare system for HTTP calls
        $configurator->call('logger', 'setLoggerTemplate', null, 'logger_http');

        // Add fallback config directory
        $configurator->addDirectory(
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Config',
            'config',
            Priority::LOWEST
        );
    }

    public function onCreateContainer(Factory $container)
    {
    }
}