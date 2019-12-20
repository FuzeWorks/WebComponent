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
use FuzeWorks\Exception\OutputException;

/**
 * @todo Implement caching
 */
class Output
{

    /**
     * The internal Input class
     *
     * @var Input
     */
    private $input;

    /**
     * The internal URI class
     *
     * @var URI
     */
    private $uri;

    /**
     * WebCfg
     *
     * @var ConfigORM
     */
    private $config;

    /**
     * Output to be sent to the client
     *
     * @var string
     */
    protected $output;

    /**
     * Headers to be sent to the client
     *
     * @var array
     */
    protected $headers = [];

    protected $compressOutput = false;

    /**
     * List of mime types
     *
     * @var array
     */
    public $mimes = [];
    protected $mimeType = 'text/html';

    /**
     * The amount of time the current page is cached
     *
     * @var int $cacheTime
     */
    protected $cacheTime = 0;

    /**
     * Whether a cache file is being used now
     *
     * @var bool
     */
    protected $usingCache = false;

    /**
     * The status code that will be sent to the client
     *
     * @var int $statusCode
     */
    protected $statusCode = 200;

    /**
     * The status code text that will be sent along with $statusCode
     *
     * @var string $statusText
     */
    protected $statusText = 'OK';

    public function init()
    {
        $this->input = Factory::getInstance()->input;
        $this->uri = Factory::getInstance()->uri;
        $this->mimes = Factory::getInstance()->config->getConfig('mimes')->toArray();
        $this->config = Factory::getInstance()->config->getConfig('web');

        $zlib = (bool) ini_get('zlib.output_compression');
        $this->compressOutput = (!$zlib && $this->config->get('compress_output') && extension_loaded('zlib'));
    }

    /**
     * Display Output
     *
     * Processes and sends finalized output data to the browser along
     * with any server headers.
     *
     * @param	string	$output	Output data override
     * @return	void
     */
    public function display(string $output = null)
    {
        // Set the output data
        $output = is_null($output) ? $this->output : $output;

        // Write cache if requested to do so
        if ($this->cacheTime > 0 && !is_null($output))
            $this->writeCache($output);

        // First send status code
        http_response_code($this->statusCode);
        @header('Status: ' . $this->statusCode . ' ' . $this->statusText, true);

        // If compression is requested, start buffering
        if (
            $this->compressOutput && !$this->usingCache &&
            !is_null($this->input->server('HTTP_ACCEPT_ENCODING')) &&
            strpos($this->input->server('HTTP_ACCEPT_ENCODING'), 'gzip') !== false
        )
        {
            Logger::log("Compressing output...");
            ob_start('ob_gzhandler');
        }

        // Send gzip headers when using cache
        if ($this->usingCache && $this->compressOutput)
        {
            if (!is_null($this->input->server('HTTP_ACCEPT_ENCODING')) &&
                strpos($this->input->server('HTTP_ACCEPT_ENCODING'), 'gzip') !== false)
            {
                header('Content-Encoding: gzip');
                header('Content-Length: '.strlen($output));
            }
            // If the cache is zipped, but the client doesn't support it, decompress the output
            else
                $output = gzinflate(substr($output, 10, -8));
        }

        // Send all available headers
        if (!empty($this->headers))
            foreach ($this->headers as $header)
                @header($header[0], $header[1]);

        echo $output;

        Logger::log('Output sent to browser');
    }

    /**
     * Enable the current page to be cached
     *
     * Set the amount of time with the $time parameter.
     *
     * @param int $time In minutes
     */
    public function cache(int $time)
    {
        $this->cacheTime = $time > 0 ? $time : 0;
    }

    public function getCache(string $selector): bool
    {
        // If output cache is disabled, don't return a cache result
        if ($this->config->get('cache_output') !== true)
            return false;

        // Generate the full uri
        $uri = $this->config->get('base_url') . (empty($selector) ? 'index' : $selector);
        $getParams = $this->input->get();

        // Determine the identifier
        $identier = md5($uri . '|' . serialize($getParams));

        // Determine the file that holds the cache
        if ($this->compressOutput)
            $file = Core::$tempDir . DS . 'OutputCache' . DS . $identier . '_gzip.fwcache';
        else
            $file = Core::$tempDir . DS . 'OutputCache' . DS . $identier . '.fwcache';

        // Determine if file exists
        if (!file_exists($file))
            return false;

        // Retrieve cache
        $cache = file_get_contents($file);

        // Verify that this is a cache file
        if (!preg_match('/^(.*)EndFuzeWorksCache--->/', $cache, $match))
            return false;

        // Retrieve data from cache file
        $cacheInfo = unserialize($match[1]);

        // Test if the cache has expired
        if (time() > $cacheInfo['expire'])
        {
            // If not writeable, log warning and do not remove
            if (!Core::isReallyWritable($file))
            {
                Logger::logWarning("Found expired output cache. Could not remove!");
                return false;
            }

            // Delete file if expired
            @unlink($file);
            Logger::logInfo("Found expired output cache. Removed.");
            return false;
        }

        // @todo Send cache header

        // Send all the headers cached in the file
        foreach ($cacheInfo['headers'] as $header)
            $this->setHeader($header[0], $header[1]);

        // And save the output
        $this->usingCache = true;
        $this->setOutput(substr($cache, strlen($match[0])));
        Logger::logInfo("Found output cache. Set output.");
        return true;
    }

    public function writeCache(string $output)
    {
        // If output cache is disabled, don't create a cache entry
        if ($this->config->get('cache_output') !== true)
            return false;

        // First create cache directory
        $cachePath = Core::$tempDir . DS . 'OutputCache';

        // Attempt to create the OutputCache directory in the TempDirectory
        if (!is_dir($cachePath) && !mkdir($cachePath, 0777, false))
        {
            Logger::logError("Could not write output cache. Cannot create directory. Are permissions set correctly?");
            return false;
        }

        // If directory is not writable, return error
        if (!Core::isReallyWritable($cachePath))
        {
            Logger::logError("Could not write output cache. No file permissions. Are permissions set correctly?");
            return false;
        }

        // Generate the full uri
        $uri = $this->config->get('base_url') . (empty($this->uri->uriString()) ? 'index' : $this->uri->uriString());
        $getParams = $this->input->get();

        // Determine the identifier
        $identier = md5($uri . '|' . serialize($getParams));

        // Determine the file that holds the cache
        if ($this->compressOutput)
            $file = $cachePath . DS . $identier . '_gzip.fwcache';
        else
            $file = $cachePath . DS . $identier . '.fwcache';


        // If compression is enabled, compress the output
        if ($this->compressOutput)
        {
            $output = gzencode($output);
            if ($this->getHeader('content-type') === null)
                $this->setContentType($this->mimeType);
        }

        // Calculate expiry time
        $expire = time() + ($this->cacheTime * 60);

        // Prepare the cache contents
        $cache = [
            'expire' => $expire,
            'headers' => $this->headers
        ];

        // Create cache file contents
        $cache = serialize($cache) . 'EndFuzeWorksCache--->' . $output;

        // Write the cache
        if (file_put_contents($file, $cache, LOCK_EX) === false)
        {
            @unlink($file);
            Logger::logError("Could not write output cache. File error. Deleting cache file.");
            return false;
        }

        // Lowering permissions to read only
        chmod($cachePath, 0640);

        // And report back
        Logger::logInfo("Output cache has been saved.");

        // @todo Set cache header
        return true;
    }

    /**
     * Get Output
     *
     * Returns the current output string.
     *
     * @return	string
     */

    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Set Output
     *
     * Sets the output string.
     *
     * @param	string	$output	Output data
     */
    public function setOutput(string $output)
    {
        $this->output = $output;
    }

    /**
     * Append Output
     *
     * Appends data onto the output string.
     *
     * @param	string	$output	Data to append
     */
    public function appendOutput(string $output)
    {
        $this->output .= $output;
    }

    /**
     * Set Header
     *
     * Lets you set a server header which will be sent with the final output.
     *
     * @param	string	$header		Header
     * @param	bool	$replace	Whether to replace the old header value, if already set
     */
    public function setHeader(string $header, bool $replace = true)
    {
        // If compression is enabled content-length should be suppressed, since it won't match the length
        // of the compressed output.
        if ($this->compressOutput && strncasecmp($header, 'content-length', 14) === 0)
            return;

        $this->headers[] = [$header, $replace];
    }

    /**
     * Get Header
     *
     * @param	string	$headerName
     * @return	string|null
     */
    public function getHeader(string $headerName)
    {
        // Combine sent headers with queued headers
        $headers = array_merge(
            array_map('array_shift', $this->headers),
            headers_list()
        );

        if (empty($headers) || empty($headerName))
            return null;

        foreach ($headers as $header)
            if (strncasecmp($headerName, $header, $l = strlen($headerName)) === 0)
                return trim(substr($header, $l+1));

        return null;
    }

    /**
     * Set Content-Type Header
     *
     * @param	string	$mimeType	Extension of the file we're outputting
     * @param	string	$charset	Character set (default: NULL)
     */
    public function setContentType(string $mimeType, $charset = null)
    {
        if (strpos($mimeType, '/') === false)
        {
            $extension = ltrim($mimeType, '.');
            if (isset($this->mimes[$extension]))
            {
                $mimeType = &$this->mimes[$extension];
                if (is_array($mimeType))
                    $mimeType = current($mimeType);
            }
        }

        $this->mimeType = $mimeType;
        if (empty($charset))
            $charset = $this->config->get('charset');

        $header = 'Content-Type: ' . $mimeType . (empty($charset) ? '' : '; charset='.$charset);
        $this->headers[] = [$header, true];
    }

    /**
     * Get Current Content-Type Header
     *
     * @return	string	'text/html', if not already set
     */
    public function getContentType(): string
    {
        foreach ($this->headers as $header)
            if (sscanf($header[0], 'Content-Type: %[^;]', $contentType) === 1)
                return $contentType;

        return 'text/html';
    }

    /**
     * Set HTTP Status Header
     *
     * @param int $code
     * @param string $text
     * @throws OutputException
     */
    public function setStatusHeader(int $code = 200, string $text = '')
    {
        $this->statusCode = $code;
        if (!empty($text))
            $this->statusText = $text;
        else
        {
            $statusCodes = [
                100 => 'Continue',
                101 => 'Switching Protocols',

                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                203 => 'Non-Authoritative Information',
                204 => 'No Content',
                205 => 'Reset Content',
                206 => 'Partial Content',

                300 => 'Multiple Choices',
                301 => 'Moved Permanently',
                302 => 'Found',
                303 => 'See Other',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                307 => 'Temporary Redirect',

                400 => 'Bad Request',
                401 => 'Unauthorized',
                402 => 'Payment Required',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Timeout',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition Failed',
                413 => 'Request Entity Too Large',
                414 => 'Request-URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Requested Range Not Satisfiable',
                417 => 'Expectation Failed',
                422 => 'Unprocessable Entity',

                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                505 => 'HTTP Version Not Supported'
            ];

            if (isset($statusCodes[$code]))
                $this->statusText = $statusCodes[$code];
            else
                throw new OutputException("Could not set status header. Code '" . $code . "' not recognized");
        }
    }

}