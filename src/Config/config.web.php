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
return [
    // General
    'base_url' => '',
    'serverName' => 'FuzeWorks',

    // Whether to allow GET parameters
    'allow_get_input' => true,

    // Clears the global $_GET, $_POST, $_COOKIE and $_SERVER arrays in order to prevent misuse
    'empty_global_arrays' => true,

    // Whether to restore the $_GET, $_POST, $_COOKIE and $_SERVER arrays when FuzeWorks shuts down
    'restore_global_arrays' => true,
    'permitted_uri_chars' => 'a-z 0-9~%.:_\-',
    'charset' => 'UTF-8',

    // Whether to redirect http traffic to https
    'redirect_to_https' => false,

    // Whether to gzip the output when the client supports it
    'compress_output' => false,

    // Global switch for output cache. To use, must be enabled in view as well
    'cache_output' => true,
    'xss_clean' => true,

    // Cookie settings
    'cookie_prefix' => 'FWZ_',
    'cookie_domain' => '',
    'cookie_path' => '/',
    'cookie_secure' => false,
    'cookie_httponly' => false
];