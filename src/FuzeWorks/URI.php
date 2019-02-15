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
use FuzeWorks\Exception\UriException;

/**
 * Class URI
 *
 * @todo Add to assoc methods
 */
class URI
{

    /**
     * ConfigORM of the Web config file
     *
     * @var ConfigORM
     */
    private $config;

    /**
     * @var Input
     */
    private $input;

    protected $baseUri;
    protected $uriString;
    protected $segments;

    public function init()
    {
        $this->input = Factory::getInstance()->input;
        $this->config = Factory::getInstance()->config->getConfig('web');
        if (WebComponent::$willHandleRequest)
            $this->determineUri();
    }

    public function determineUri()
    {
        // If no base_url is provided, attempt to determine URI with SERVER variables
        if (empty($this->config->get('base_url')))
        {
            $serverAddr = $this->input->server('SERVER_ADDR');
            if (!is_null($serverAddr))
            {
                if (strpos($serverAddr, ':') !== false)
                    $serverAddr = '['.$serverAddr.']';

                $scriptName = $this->input->server('SCRIPT_NAME');
                $scriptFilename = $this->input->server('SCRIPT_FILENAME');
                $baseUrl = ($this->isHttps() ? 'https' : 'http') .
                    "://" . $serverAddr .
                    substr($scriptName, 0, strpos($scriptName, basename($scriptFilename)));
            }
            else
                $baseUrl = 'http://localhost/';

            $this->config->set('base_url', $baseUrl);
        }

        // Set the baseUri
        $this->baseUri = $this->config->get('base_url');
        $subUri = $this->parseUri();

        // Log the incoming request
        Logger::newLevel("Request received with the following URL: ");
        Logger::logInfo("Base URL: " . $this->baseUri);
        Logger::logInfo("Request URL: ". $subUri);
        Logger::stopLevel();

        $this->setUri($this->parseUri());
        return true;
    }

    public function uriString(): string
    {
        return $this->uriString;
    }

    /**
     * Fetch URI Segment
     *
     * @param   int     $n      index
     * @return  string|null
     */
    public function segment(int $n)
    {
        return isset($this->segments[$n]) ? $this->segments[$n] : null;
    }

    /**
     * Segment Array
     *
     * @return array
     */
    public function segmentArray(): array
    {
        return $this->segments;
    }

    protected function parseUri(): string
    {
        // If no vars are provided, return an empty string
        $vars = $this->input->server(['REQUEST_URI', 'SCRIPT_NAME', 'QUERY_STRING']);
        if (is_null($vars['REQUEST_URI']) || is_null($vars['SCRIPT_NAME']))
            return '';

        // Get a basic URL from parse_url
        $uri = parse_url('http://dummy'.$vars['REQUEST_URI']);
        $uri = isset($uri['path']) ? $uri['path'] : '';

        // Determine the script
        if (isset($vars['SCRIPT_NAME'][0]))
        {
            if (strpos($uri, $vars['SCRIPT_NAME']) === 0)
                $uri = (string) substr($uri, strlen($vars['SCRIPT_NAME']));
            elseif (strpos($uri, dirname($vars['SCRIPT_NAME'])) === 0)
                $uri = (string) substr($uri, strlen(dirname($vars['SCRIPT_NAME'])));

        }

        // If empty, return empty
        if ($uri === '/' || $uri === '')
            return '/';

        // Remove the relative directory
        $uris = [];
        $tok = strtok($uri, '/');
        while ($tok !== false)
        {
            if ( (!empty($tok) || $tok === '0') && $tok !== '..')
                $uris[] = $tok;

            $tok = strtok('/');
        }

        return implode('/', $uris);
    }

    /**
     * @param $str
     * @throws UriException
     */
    protected function setUri($str)
    {
        // First clean the string
        $uri = $this->uriString = trim(UTF8::removeInvisibleCharacters($str, false), '/');
        if ($this->uriString === '')
            return;

        // Determine the segments
        $this->segments[0] = null;
        foreach (explode('/', trim($uri, '/')) as $segment)
        {
            // Filter segments for security
            $segment = trim($segment);
            $this->filterUri($segment);

            if ($segment !== '')
                $this->segments[] = $segment;
        }

        unset($this->segments[0]);
    }

    /**
     * Filter URI
     *
     * Filters segments for malicious characters.
     *
     * @param string $str
     * @return bool
     * @throws UriException
     */
    protected function filterUri(string &$str): bool
    {
        $permitted = $this->config->get('permitted_uri_chars');
        if (
            !empty($str) &&
            !empty($permitted) &&
            !preg_match('/^['.$permitted.']+$/i'.(UTF8::$isEnabled ? 'u' : ''), $str))
        {
            throw new UriException("The submitted URI has illegal characters.");
        }

        return true;
    }

    /**
     * Is HTTPS?
     *
     * Determines if the application is accessed via an encrypted
     * (HTTPS) connection.
     *
     * @return  bool
     */
    protected function isHttps(): bool
    {
        $https = $this->input->server('HTTPS');
        if (!is_null($https) && strtolower($https) !== 'off')
        {
            return true;
        }
        elseif (!is_null($this->input->server('HTTP_X_FORWARDED_PROTO')) && $this->input->server('HTTP_X_FORWARDED_PROTO') === 'https')
        {
            return true;
        }
        elseif ( ! is_null($this->input->server('HTTP_FRONT_END_HTTPS')) && strtolower($this->input->server('HTTP_FRONT_END_HTTPS')) !== 'off')
        {
            return true;
        }

        return false;
    }

}