<?php /** @noinspection ALL */

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
use Tracy\Debugger;

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

    /**
     * @var Security
     */
    private $security;

    public function init()
    {
        // Set dependencies first
        $this->security = Factory::getInstance()->security;

        // Set the configuration
        $this->webConfig = Factory::getInstance()->config->getConfig('web');

        // Sanitize all global arrays
        $this->sanitizeGlobals();

        if ($this->webConfig->get('empty_global_arrays') && $this->webConfig->get('restore_global_arrays'))
        {
            if (class_exists('\FuzeWorks\TracyComponent', true) && \FuzeWorks\TracyComponent::isEnabled())
            {
                set_exception_handler([$this, 'tracyExceptionHandler']);
                set_error_handler([$this, 'tracyErrorHandler']);
            }
            Events::addListener(
                [$this, 'restoreGlobalArrays'],
                'coreShutdownEvent', Priority::HIGHEST
            );
        }
    }

    /**
     * Used to restore global arrays before handling errors by Tracy
     *
     * @param $exception
     * @param bool $exit
     * @internal
     */
    public function tracyExceptionHandler($exception, $exit = true)
    {
        $this->restoreGlobalArrays();
        Debugger::exceptionHandler($exception, $exit);
    }

    /**
     * Used to restore global arrays before handling errors by Tracy
     *
     * @param $severity
     * @param $message
     * @param $file
     * @param $line
     * @param array $context
     * @throws \ErrorException
     * @internal
     */
    public function tracyErrorHandler($severity, $message, $file, $line, $context = [])
    {
        $this->restoreGlobalArrays();
        Debugger::errorHandler($severity, $message, $file, $line, $context);
    }

    public function restoreGlobalArrays()
    {
        Logger::logInfo('Restoring global $_GET, $_POST, $_SERVER, $_COOKIE arrays');
        $_GET = $this->inputArray['get'];
        $_POST = $this->inputArray['post'];
        $_COOKIE = $this->inputArray['cookie'];
        $_SERVER = $this->inputArray['server'];
    }

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

        // Clean GET
        foreach ($this->inputArray['get'] as $key => $val)
            $this->inputArray[$this->cleanInputKeys($key)] = $this->cleanInputData($val);

        // Clean POST
        foreach ($this->inputArray['post'] as $key => $val)
            $this->inputArray[$this->cleanInputKeys($key)] = $this->cleanInputData($val);

        // Clean COOKIE
        if (!empty($this->inputArray['cookie']))
        {
            // Get rid of conflicting cookies
            unset(
                $this->inputArray['cookie']['$Version'],
                $this->inputArray['cookie']['$Path'],
                $this->inputArray['cookie']['$Domain']
            );

            foreach ($this->inputArray['cookie'] as $key => $val)
            {
                if (($cookie_key = $this->cleanInputKeys($key)) !== false)
                    $this->inputArray['cookie'][$cookie_key] = $this->cleanInputData($val);
                else
                    unset($this->inputArray['cookie'][$key]);
            }
        }

        // Sanitize PHP_SELF
        $this->inputArray['server']['PHP_SELF'] = strip_tags($this->inputArray['server']['PHP_SELF']);
        Logger::logInfo("Global variables sanitized");
    }

    /**
     * Clean Keys
     *
     * Internal method that helps to prevent malicious users
     * from trying to exploit keys we make sure that keys are
     * only named with alpha-numeric text and a few other items.
     *
     * @param	string	$str	Input string
     * @param	bool	$fatal	Whether to terminate script exection
     *				or to return FALSE if an invalid
     *				key is encountered
     * @return	string|bool
     */
    protected function cleanInputKeys(string $str, bool $fatal = true)
    {
        if (!preg_match('/^[a-z0-9:_\/|-]+$/i', $str))
        {
            if ($fatal)
                return false;
            else
            {
                // @todo Implement status header 503
                exit(7);
            }
        }

        // Clean with UTF8, if supported
        if (UTF8::$isEnabled)
            return UTF8::cleanString($str);

        return $str;
    }

    /**
     * Clean Input Data
     *
     * Internal method that aids in escaping data and
     * standardizing newline characters to PHP_EOL.
     *
     * @param	string|string[]	$str	Input string(s)
     * @return	string|array
     */
    protected function cleanInputData($str)
    {
        if (is_array($str))
        {
            $new = [];
            foreach (array_keys($str) as $key)
                $new[$this->cleanInputKeys($key)] = $this->cleanInputData($str[$key]);

            return $new;
        }

        // Clean with UTF8 if supported
        if (UTF8::$isEnabled)
            $str = UTF8::cleanString($str);

        // Remove invisible characters
        $str = UTF8::removeInvisibleCharacters($str, false);

        // Standardize newlines (@todo)

        return $str;
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

        return ($xssClean === true ? $this->security->xss_clean($value) : $value);
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