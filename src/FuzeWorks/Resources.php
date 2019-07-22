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

use FuzeWorks\Event\ResourceServeEvent;
use FuzeWorks\Exception\WebException;

/**
 * FuzeWorks' handler for static resources.
 *
 * Objects in FuzeWorks can register a folder with static resources, which shall be served if requested by clients.
 * This system should be avoided for high-performance applications. It is recommended to make special configurations in the web server
 * in those kinds of cases.
 */
class Resources
{

    private $resources = [];

    /**
     * @var Output
     */
    private $output;

    public function init()
    {
        $this->output = Factory::getInstance()->output;
    }

    public function resourceExists(array $resourceUrlSegments): bool
    {
        // First find the resource
        $file = $this->findResource($resourceUrlSegments);

        // If not found, return false;
        if (is_null($file))
            return false;

        // If found, simply return true
        return true;
    }

    /**
     * Serves a static file if found.
     *
     * @param array $resourceUrlSegments
     * @return bool
     * @throws WebException
     *
     * @todo Bypass the Output system and use the readFile() method.
     * @todo Run as FuzeWorks pre-code, before creating the container
     */
    public function serveResource(array $resourceUrlSegments): bool
    {
        // First find the resource
        $file = $this->findResource($resourceUrlSegments);

        // If not found return false
        if (is_null($file))
            return false;

        // If a file is found, fire a serveResourceEvent
        /** @var ResourceServeEvent $event */
        try {
            $event = Events::fireEvent('resourceServeEvent', $file['resourceName'], $file['segments'], $file['file']);
        } catch (Exception\EventException $e) {
            throw new WebException("Could not serve resource. resourceServeEvent threw exception: '" . $e->getMessage() . "'");
        }

        // If cancelled, don't serve
        if ($event->isCancelled())
            return false;

        // Log the resource serving
        Logger::log("Serving static resource '/" . $file['resourceName'] . '/' . implode('/', $file['segments']) . "'");

        // Serve file in accordance with event
        $fileExtension = pathinfo($event->resourceFilePath, PATHINFO_EXTENSION);
        $this->output->setContentType($fileExtension);
        $this->output->setOutput(file_get_contents($event->resourceFilePath));
        #readfile($event->resourceFilePath);

        // And return true at the end
        return true;
    }

    protected function findResource(array $resourceUrlSegments): ?array
    {
        // If too few segments provided, don't even bother
        if (count($resourceUrlSegments) < 2)
            return null;

        // First segment should be the resourceName, check if it exists
        $resourceName = urldecode($resourceUrlSegments[1]);
        if (!isset($this->resources[$resourceName]))
            return null;

        // If resource is found, generate file path
        $resourceUrlSegmentsBck = $resourceUrlSegments;
        array_shift($resourceUrlSegments);
        $file = $this->resources[$resourceName] . DS . implode(DS, $resourceUrlSegments);

        // Test if file exists, if it does, return the string
        if (file_exists($file) && is_file($file))
            return ['file' => $file, 'resourceName' => $resourceName, 'segments' => $resourceUrlSegments];

        return null;
    }

    /**
     * Register a resource which can be served statically.
     *
     * The resourceName will be the directory under which the files shall be served on the web server.
     * The filePath is where FuzeWorks should look for the files.
     *
     * @param string $resourceName
     * @param string $filePath
     * @throws WebException
     * @return bool
     */
    public function registerResources(string $resourceName, string $filePath): bool
    {
        // First check if the resource already exists
        $resourceName = urldecode($resourceName);
        if (isset($this->resources[$resourceName]))
            throw new WebException("Could not register resources. Resources with same name already exists.");

        // Also check if the file path exists and is a directory
        if (!file_exists($filePath) && !is_dir($filePath))
            throw new WebException("Could not register resources. Provided filePath does not exist.");

        // Add the resource
        $this->resources[$resourceName] = $filePath;

        // Log the registration
        Logger::log("Adding static resources on: '/" . $resourceName . "'");

        return true;
    }



}