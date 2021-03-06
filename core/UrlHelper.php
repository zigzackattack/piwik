<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik
 * @package Piwik
 */
namespace Piwik;

/**
 * Class UrlHelper
 * @package Piwik
 *
 * @api
 */
class UrlHelper
{
    /**
     * Returns a Query string,
     * Given an array of input parameters, and an array of parameter names to exclude
     *
     * @static
     * @param $queryParameters
     * @param $parametersToExclude
     * @return string
     */
    public static function getQueryStringWithExcludedParameters($queryParameters, $parametersToExclude)
    {
        $validQuery = '';
        $separator = '&';
        foreach ($queryParameters as $name => $value) {
            // decode encoded square brackets
            $name = str_replace(array('%5B', '%5D'), array('[', ']'), $name);

            if (!in_array(strtolower($name), $parametersToExclude)) {
                if (is_array($value)) {
                    foreach ($value as $param) {
                        if ($param === false) {
                            $validQuery .= $name . '[]' . $separator;
                        } else {
                            $validQuery .= $name . '[]=' . $param . $separator;
                        }
                    }
                } else if ($value === false) {
                    $validQuery .= $name . $separator;
                } else {
                    $validQuery .= $name . '=' . $value . $separator;
                }
            }
        }
        $validQuery = substr($validQuery, 0, -strlen($separator));
        return $validQuery;
    }

    /**
     * Reduce URL to more minimal form.  2 letter country codes are
     * replaced by '{}', while other parts are simply removed.
     *
     * Examples:
     *   www.example.com -> example.com
     *   search.example.com -> example.com
     *   m.example.com -> example.com
     *   de.example.com -> {}.example.com
     *   example.de -> example.{}
     *   example.co.uk -> example.{}
     *
     * @param string $url
     * @return string
     */
    public static function getLossyUrl($url)
    {
        static $countries;
        if (!isset($countries)) {
            $countries = implode('|', array_keys(Common::getCountriesList(true)));
        }

        return preg_replace(
            array(
                 '/^(w+[0-9]*|search)\./',
                 '/(^|\.)m\./',
                 '/(\.(com|org|net|co|it|edu))?\.(' . $countries . ')(\/|$)/',
                 '/(^|\.)(' . $countries . ')\./',
            ),
            array(
                 '',
                 '$1',
                 '.{}$4',
                 '$1{}.',
            ),
            $url);
    }

    /**
     * Returns true if the string passed may be a URL.
     * We don't need a precise test here because the value comes from the website
     * tracked source code and the URLs may look very strange.
     *
     * @param string $url
     * @return bool
     */
    public static function isLookLikeUrl($url)
    {
        return preg_match('~^(ftp|news|http|https)?://(.*)$~D', $url, $matches) !== 0
        && strlen($matches[2]) > 0;
    }

    /**
     * Builds a URL from the result of parse_url function
     * Copied from the PHP comments at http://php.net/parse_url
     * @param array $parsed
     * @return bool|string
     */
    public static function getParseUrlReverse($parsed)
    {
        if (!is_array($parsed)) {
            return false;
        }

        $uri = !empty($parsed['scheme']) ? $parsed['scheme'] . ':' . (!strcasecmp($parsed['scheme'], 'mailto') ? '' : '//') : '';
        $uri .= !empty($parsed['user']) ? $parsed['user'] . (!empty($parsed['pass']) ? ':' . $parsed['pass'] : '') . '@' : '';
        $uri .= !empty($parsed['host']) ? $parsed['host'] : '';
        $uri .= !empty($parsed['port']) ? ':' . $parsed['port'] : '';

        if (!empty($parsed['path'])) {
            $uri .= (!strncmp($parsed['path'], '/', 1))
                ? $parsed['path']
                : ((!empty($uri) ? '/' : '') . $parsed['path']);
        }

        $uri .= !empty($parsed['query']) ? '?' . $parsed['query'] : '';
        $uri .= !empty($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        return $uri;
    }

    /**
     * Returns an URL query string in an array format
     *
     * @param string $urlQuery
     * @return array  array( param1=> value1, param2=>value2)
     */
    public static function getArrayFromQueryString($urlQuery)
    {
        if (strlen($urlQuery) == 0) {
            return array();
        }
        if ($urlQuery[0] == '?') {
            $urlQuery = substr($urlQuery, 1);
        }
        $separator = '&';

        $urlQuery = $separator . $urlQuery;
        //		$urlQuery = str_replace(array('%20'), ' ', $urlQuery);
        $referrerQuery = trim($urlQuery);

        $values = explode($separator, $referrerQuery);

        $nameToValue = array();

        foreach ($values as $value) {
            $pos = strpos($value, '=');
            if ($pos !== false) {
                $name = substr($value, 0, $pos);
                $value = substr($value, $pos + 1);
                if ($value === false) {
                    $value = '';
                }
            } else {
                $name = $value;
                $value = false;
            }
            if (!empty($name)) {
                $name = Common::sanitizeInputValue($name);
            }
            if (!empty($value)) {
                $value = Common::sanitizeInputValue($value);
            }

            // if array without indexes
            $count = 0;
            $tmp = preg_replace('/(\[|%5b)(]|%5d)$/i', '', $name, -1, $count);
            if (!empty($tmp) && $count) {
                $name = $tmp;
                if (isset($nameToValue[$name]) == false || is_array($nameToValue[$name]) == false) {
                    $nameToValue[$name] = array();
                }
                array_push($nameToValue[$name], $value);
            } else if (!empty($name)) {
                $nameToValue[$name] = $value;
            }
        }
        return $nameToValue;
    }

    /**
     * Returns the value of a GET parameter $parameter in an URL query $urlQuery
     *
     * @param string $urlQuery result of parse_url()['query'] and htmlentitied (& is &amp;) eg. module=test&amp;action=toto or ?page=test
     * @param string $parameter
     * @return string|bool  Parameter value if found (can be the empty string!), null if not found
     */
    public static function getParameterFromQueryString($urlQuery, $parameter)
    {
        $nameToValue = self::getArrayFromQueryString($urlQuery);
        if (isset($nameToValue[$parameter])) {
            return $nameToValue[$parameter];
        }
        return null;
    }

    /**
     * Returns the path and query part from a URL.
     * Eg. http://piwik.org/test/index.php?module=CoreHome will return /test/index.php?module=CoreHome
     *
     * @param string $url either http://piwik.org/test or /
     * @return string
     */
    public static function getPathAndQueryFromUrl($url)
    {
        $parsedUrl = parse_url($url);
        $result = '';
        if (isset($parsedUrl['path'])) {
            $result .= substr($parsedUrl['path'], 1);
        }
        if (isset($parsedUrl['query'])) {
            $result .= '?' . $parsedUrl['query'];
        }
        return $result;
    }


    /**
     * Extracts a keyword from a raw not encoded URL.
     * Will only extract keyword if a known search engine has been detected.
     * Returns the keyword:
     * - in UTF8: automatically converted from other charsets when applicable
     * - strtolowered: "QUErY test!" will return "query test!"
     * - trimmed: extra spaces before and after are removed
     *
     * Lists of supported search engines can be found in /core/DataFiles/SearchEngines.php
     * The function returns false when a keyword couldn't be found.
     *     eg. if the url is "http://www.google.com/partners.html" this will return false,
     *       as the google keyword parameter couldn't be found.
     *
     * @see unit tests in /tests/core/Common.test.php
     * @param string $referrerUrl URL referrer URL, eg. $_SERVER['HTTP_REFERER']
     * @return array|bool   false if a keyword couldn't be extracted,
     *                        or array(
     *                            'name' => 'Google',
     *                            'keywords' => 'my searched keywords')
     */
    public static function extractSearchEngineInformationFromUrl($referrerUrl)
    {
        $referrerParsed = @parse_url($referrerUrl);
        $referrerHost = '';
        if (isset($referrerParsed['host'])) {
            $referrerHost = $referrerParsed['host'];
        }
        if (empty($referrerHost)) {
            return false;
        }
        // some search engines (eg. Bing Images) use the same domain
        // as an existing search engine (eg. Bing), we must also use the url path
        $referrerPath = '';
        if (isset($referrerParsed['path'])) {
            $referrerPath = $referrerParsed['path'];
        }

        // no search query
        if (!isset($referrerParsed['query'])) {
            $referrerParsed['query'] = '';
        }
        $query = $referrerParsed['query'];

        // Google Referrers URLs sometimes have the fragment which contains the keyword
        if (!empty($referrerParsed['fragment'])) {
            $query .= '&' . $referrerParsed['fragment'];
        }

        $searchEngines = Common::getSearchEngineUrls();

        $hostPattern = self::getLossyUrl($referrerHost);
        if (array_key_exists($referrerHost . $referrerPath, $searchEngines)) {
            $referrerHost = $referrerHost . $referrerPath;
        } elseif (array_key_exists($hostPattern . $referrerPath, $searchEngines)) {
            $referrerHost = $hostPattern . $referrerPath;
        } elseif (array_key_exists($hostPattern, $searchEngines)) {
            $referrerHost = $hostPattern;
        } elseif (!array_key_exists($referrerHost, $searchEngines)) {
            if (!strncmp($query, 'cx=partner-pub-', 15)) {
                // Google custom search engine
                $referrerHost = 'google.com/cse';
            } elseif (!strncmp($referrerPath, '/pemonitorhosted/ws/results/', 28)) {
                // private-label search powered by InfoSpace Metasearch
                $referrerHost = 'wsdsold.infospace.com';
            } elseif (strpos($referrerHost, '.images.search.yahoo.com') != false) {
                // Yahoo! Images
                $referrerHost = 'images.search.yahoo.com';
            } elseif (strpos($referrerHost, '.search.yahoo.com') != false) {
                // Yahoo!
                $referrerHost = 'search.yahoo.com';
            } else {
                return false;
            }
        }
        $searchEngineName = $searchEngines[$referrerHost][0];
        $variableNames = null;
        if (isset($searchEngines[$referrerHost][1])) {
            $variableNames = $searchEngines[$referrerHost][1];
        }
        if (!$variableNames) {
            $searchEngineNames = Common::getSearchEngineNames();
            $url = $searchEngineNames[$searchEngineName];
            $variableNames = $searchEngines[$url][1];
        }
        if (!is_array($variableNames)) {
            $variableNames = array($variableNames);
        }

        $key = null;
        if ($searchEngineName === 'Google Images'
            || ($searchEngineName === 'Google' && strpos($referrerUrl, '/imgres') !== false)
        ) {
            if (strpos($query, '&prev') !== false) {
                $query = urldecode(trim(self::getParameterFromQueryString($query, 'prev')));
                $query = str_replace('&', '&amp;', strstr($query, '?'));
            }
            $searchEngineName = 'Google Images';
        } else if ($searchEngineName === 'Google'
            && (strpos($query, '&as_') !== false || strpos($query, 'as_') === 0)
        ) {
            $keys = array();
            $key = self::getParameterFromQueryString($query, 'as_q');
            if (!empty($key)) {
                array_push($keys, $key);
            }
            $key = self::getParameterFromQueryString($query, 'as_oq');
            if (!empty($key)) {
                array_push($keys, str_replace('+', ' OR ', $key));
            }
            $key = self::getParameterFromQueryString($query, 'as_epq');
            if (!empty($key)) {
                array_push($keys, "\"$key\"");
            }
            $key = self::getParameterFromQueryString($query, 'as_eq');
            if (!empty($key)) {
                array_push($keys, "-$key");
            }
            $key = trim(urldecode(implode(' ', $keys)));
        }

        if ($searchEngineName === 'Google') {
            // top bar menu
            $tbm = self::getParameterFromQueryString($query, 'tbm');
            switch ($tbm) {
                case 'isch':
                    $searchEngineName = 'Google Images';
                    break;
                case 'vid':
                    $searchEngineName = 'Google Video';
                    break;
                case 'shop':
                    $searchEngineName = 'Google Shopping';
                    break;
            }
        }

        if (empty($key)) {
            foreach ($variableNames as $variableName) {
                if ($variableName[0] == '/') {
                    // regular expression match
                    if (preg_match($variableName, $referrerUrl, $matches)) {
                        $key = trim(urldecode($matches[1]));
                        break;
                    }
                } else {
                    // search for keywords now &vname=keyword
                    $key = self::getParameterFromQueryString($query, $variableName);
                    $key = trim(urldecode($key));

                    // Special case: Google & empty q parameter
                    if (empty($key)
                        && $variableName == 'q'

                        && (
                            // Google search with no keyword
                            ($searchEngineName == 'Google'
                                && ( // First, they started putting an empty q= parameter
                                    strpos($query, '&q=') !== false
                                    || strpos($query, '?q=') !== false
                                    // then they started sending the full host only (no path/query string)
                                    || (empty($query) && (empty($referrerPath) || $referrerPath == '/') && empty($referrerParsed['fragment']))
                                )
                            )
                            // search engines with no keyword
                            || $searchEngineName == 'Google Images'
                            || $searchEngineName == 'DuckDuckGo')
                    ) {
                        $key = false;
                    }
                    if (!empty($key)
                        || $key === false
                    ) {
                        break;
                    }
                }
            }
        }

        // $key === false is the special case "No keyword provided" which is a Search engine match
        if ($key === null
            || $key === ''
        ) {
            return false;
        }

        if (!empty($key)) {
            if (function_exists('iconv')
                && isset($searchEngines[$referrerHost][3])
            ) {
                // accepts string, array, or comma-separated list string in preferred order
                $charsets = $searchEngines[$referrerHost][3];
                if (!is_array($charsets)) {
                    $charsets = explode(',', $charsets);
                }

                if (!empty($charsets)) {
                    $charset = $charsets[0];
                    if (count($charsets) > 1
                        && function_exists('mb_detect_encoding')
                    ) {
                        $charset = mb_detect_encoding($key, $charsets);
                        if ($charset === false) {
                            $charset = $charsets[0];
                        }
                    }

                    $newkey = @iconv($charset, 'UTF-8//IGNORE', $key);
                    if (!empty($newkey)) {
                        $key = $newkey;
                    }
                }
            }

            $key = Common::mb_strtolower($key);
        }

        return array(
            'name'     => $searchEngineName,
            'keywords' => $key,
        );
    }
}