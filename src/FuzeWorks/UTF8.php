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


class UTF8
{

    public static $isEnabled = true;

    /**
     * Initializer for UTF-8
     *
     * Determines if UTF-8 support is to be enabled
     * @return void
     */
    public static function init()
    {
        try {
            $charset = strtoupper(Factory::getInstance()->config->getConfig('web')->get('charset'));
        } catch (Exception\ConfigException $e) {
            $charset = 'UTF-8';
        }
        ini_set('default_charset', $charset);

        // Enable mbstring if it is provided
        if (extension_loaded('mbstring'))
        {
            define('MBEnabled', true);
            mb_internal_encoding($charset);
            mb_substitute_character('none');
        }
        else
            define('MBEnabled', false);

        // Enable iconv if it is provided
        if (extension_loaded('iconv'))
        {
            define('ICONVEnabled', true);
            ini_set('default_encoding', $charset);
        }
        else
            define('ICONVEnabled', false);

        // Set some global values
        ini_set('php.internal_encoding', $charset);
        if (defined('PREG_BAD_UTF8_ERROR')
            && (ICONVEnabled || MBEnabled)
            && $charset === 'UTF-8')
        {
            self::$isEnabled = true;
            Logger::logInfo('UTF-8 support has been enabled');
        }
        else
        {
            self::$isEnabled = false;
            Logger::logInfo('UTF-8 support has not been enabled');
        }
    }

    /**
     * Clean UTF-8 strings
     *
     * Ensures strings contain only valid UTF-8 characters.
     *
     * @param   string  $str    String to clean
     * @return  string
     */
    public static function cleanString(string $str): string
    {
        if (self::isAscii($str) === false)
        {
            if (MBEnabled)
                $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
            elseif (ICONVEnabled)
                $str = @iconv('UTF-8', 'UTF-8//IGNORE', $str);
        }

        return $str;
    }

    /**
     * Convert to UTF-8
     *
     * Attempts to convert a string to UTF-8.
     *
     * @param string $str
     * @param string $encoding
     * @return bool|string
     */
    public static function convertToUtf8(string $str, string $encoding)
    {
        if (MBEnabled)
            return mb_convert_encoding($str, 'UTF-8', $encoding);
        elseif (ICONVEnabled)
            return @iconv($encoding, 'UTF-8', $str);

        return false;
    }

    /**
     * Is ASCII?
     *
     * Tests if a string is standard 7-bit ASCII or not.
     *
     * @param   string  $str    String to check
     * @return  bool
     */
    public static function isAscii(string $str): bool
    {
        return (preg_match('/[^\x00-\x7F]/S', $str) === 0);
    }

    /**
     * Remove Invisible Characters
     *
     * This prevents sandwiching null characters
     * between ascii characters, like Java\0script.
     *
     * @param   string
     * @param   bool
     * @return  string
     */

    public static function removeInvisibleCharacters($str, $urlEncoded = true): string
    {
        // First determine which characters are invisible
        if ($urlEncoded)
            $nonDisplayable = ['/%0[0-8bcef]/', '/%1[0-9a-f]/'];
        else
            $nonDisplayable = [];

        $nonDisplayable[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';

        do
        {
            $str = preg_replace($nonDisplayable, '', $str, -1, $count);
        }
        while($count);

        return $str;
    }

}