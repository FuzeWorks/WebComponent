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

use FuzeWorks\ConfigORM\ConfigORM;
use FuzeWorks\Event\NotifierEvent;

class Input
{
    /**
     * Array with all the values from $_GET, $_POST, $_SERVER, $_COOKIE
     *
     * @var array
     */
    protected $inputArray = [];

    /**
     * Config of the WebComponent
     *
     * @var ConfigORM
     */
    protected $webConfig;

    public function init()
    {
        // Set the configuration
        $this->webConfig = Factory::getInstance()->config->getConfig('web');

        // Sanitize all global arrays
        $this->sanitizeGlobals();

        if ($this->webConfig->get('empty_global_arrays') && $this->webConfig->get('restore_global_arrays'))
            Events::addListener(
                array($this, 'restoreGlobalArrays'),
                'coreShutdownEvent', Priority::HIGH
            );
    }

    public function restoreGlobalArrays(NotifierEvent $event)
    {
        $_GET = $this->inputArray['get'];
        $_POST = $this->inputArray['post'];
        $_COOKIE = $this->inputArray['cookie'];
        $_SERVER = $this->inputArray['server'];
    }

    /**
     * @todo Do this later
     */
    protected function sanitizeGlobals()
    {
        // Copy all values from the global arrays into a local inputArray
        $this->inputArray['get'] = ($this->webConfig->get('allow_get_input') ? $_GET : []);
        $this->inputArray['post'] = $_POST;
        $this->inputArray['cookie'] = $_COOKIE;
        $this->inputArray['server'] = $_SERVER;

        // If required to, empty the global arrays
        if ($this->webConfig->get('empty_global_arrays'))
            unset($_GET, $_POST, $_COOKIE, $_SERVER);
    }

    /**
     * @param string $arrayName
     * @param null $index
     * @param bool $xssClean
     * @return mixed
     */
    protected function getFromInputArray(string $arrayName, $index = null, bool $xssClean = true)
    {
        // Clean XSS if requested manually or forced through configuration
        $xssClean = $xssClean || $this->webConfig->get('xss_clean');

        // If the index is null, the entire array is requested
        $index = (!is_null($index) ? $index : array_keys($this->inputArray[$arrayName]));

        // If the requested index is an array, fetch all requested fields
        if (is_array($index))
        {
            $values = [];
            foreach ($index as $key)
                $values[$key] = $this->getFromInputArray($arrayName, $key, $xssClean);
            return $values;
        }

        // If the requested index is a string and found, take the value
        if (isset($this->inputArray[$arrayName][$index]))
            $value = $this->inputArray[$arrayName][$index];
        else
            return null;

        // @todo Implement XSS Clean here

        return $value;
    }

    public function get($index = null, bool $xssClean = true)
    {
        return $this->getFromInputArray('get', $index, $xssClean);
    }

    public function post($index = null, bool $xssClean = true)
    {
        return $this->getFromInputArray('post', $index, $xssClean);
    }

    public function postGet($index, bool $xssClean = true)
    {
        return isset($this->inputArray['post'][$index]) ? $this->post($index, $xssClean) : $this->get($index, $xssClean);
    }

    public function getPost($index, bool $xssClean = true)
    {
        return isset($this->inputArray['get'][$index]) ? $this->get($index, $xssClean) : $this->post($index, $xssClean);
    }

    public function cookie($index = null, bool $xssClean = true)
    {
        return $this->getFromInputArray('cookie', $index, $xssClean);
    }

    public function server($index = null, bool $xssClean = true)
    {
        return $this->getFromInputArray('server', $index, $xssClean);
    }

    /**
     * @todo Extend with OldInput functionality
     */
    public function ip()
    {
        $ip = '';
        // Validate IP

        $valid = (
            (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ||
            (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)
        );
    }

    public function userAgent(bool $xssClean = true): string
    {
        return $this->getFromInputArray('server', 'HTTP_USER_AGENT', $xssClean);
    }

    public function method(bool $xssClean = true): string
    {
        return $this->getFromInputArray('server', 'REQUEST_METHOD', $xssClean);
    }


}