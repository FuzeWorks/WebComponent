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
    /*
	|--------------------------------------------------------------------------
	| Cross Site Request Forgery
	|--------------------------------------------------------------------------
	| Enables a CSRF cookie token to be set. When set to TRUE, token will be
	| checked on a submitted form. If you are accepting user data, it is strongly
	| recommended CSRF protection be enabled.
	|
	| 'csrf_token_name' = The token name
	| 'csrf_cookie_name' = The cookie name
	| 'csrf_expire' = The number in seconds the token should expire.
	| 'csrf_regenerate' = Regenerate token on every submission
	| 'csrf_exclude_uris' = Array of URIs which ignore CSRF checks
	*/
    'csrf_protection' => true,
    'csrf_token_name' => 'fw_csrf_token',
    'csrf_expire' => 7200,
    'csrf_exclude_uris' => array(),

    // CSRF Cookie information
    'csrf_cookie_name' => 'fw_csrf_cookie',
    'csrf_cookie_prefix' => '',
    'csrf_cookie_domain' => '',
    'csrf_cookie_path' => '/',
    'csrf_cookie_secure' => false,
    'csrf_cookie_httponly' => false
];