<?php
/**
 * Click Tracker Module - Traffic source classification
 *
 * Derives the traffic source of a click from the page (or landing) URL parameters
 * and the referrer host. Only derived values are stored, never the full referrer URL.
 *
 * @author    Ettore Stani
 * @copyright 2024 Ettore Stani
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class ClickTrackerSource
{
    const GOOGLE_ADS = 'google_ads';
    const GOOGLE_ORGANIC = 'google_organic';
    const META = 'meta';
    const SEARCH = 'search';
    const SOCIAL = 'social';
    const EMAIL = 'email';
    const CAMPAIGN = 'campaign';
    const REFERRAL = 'referral';
    const DIRECT = 'direct';
    const INTERNAL = 'internal';
    const UNKNOWN = 'unknown';

    /**
     * All source codes, in display order
     *
     * @return array
     */
    public static function getSources()
    {
        return array(
            self::GOOGLE_ADS,
            self::GOOGLE_ORGANIC,
            self::META,
            self::SEARCH,
            self::SOCIAL,
            self::EMAIL,
            self::CAMPAIGN,
            self::REFERRAL,
            self::DIRECT,
            self::INTERNAL,
            self::UNKNOWN,
        );
    }

    /**
     * Classify a visit
     *
     * @param string $url Page (or landing) URL, query string included
     * @param string|null $referrer Referrer URL; null when unknown (e.g. data recorded before 1.2.0)
     * @param array $shopHosts Lower-case hosts of the shop, used to detect internal navigation
     * @return array traffic_source, utm_source, utm_medium, utm_campaign, referrer_host
     */
    public static function classify($url, $referrer, array $shopHosts = array())
    {
        $params = self::getQueryParams($url);

        $result = array(
            'traffic_source' => null,
            'utm_source' => self::cleanParam($params, 'utm_source', 100),
            'utm_medium' => self::cleanParam($params, 'utm_medium', 100),
            'utm_campaign' => self::cleanParam($params, 'utm_campaign', 255),
            'referrer_host' => null,
        );

        $referrerHost = null;
        if (is_string($referrer) && $referrer !== '') {
            $host = parse_url($referrer, PHP_URL_HOST);
            if (is_string($host) && preg_match('/^[a-z0-9.\-]{1,255}$/i', $host)) {
                $referrerHost = Tools::strtolower($host);
            }
        }

        $isInternal = $referrerHost !== null && in_array($referrerHost, $shopHosts, true);
        if ($referrerHost !== null && !$isInternal) {
            $result['referrer_host'] = $referrerHost;
        }

        $result['traffic_source'] = self::detectSource($params, $result, $referrer, $referrerHost, $isInternal);

        return $result;
    }

    /**
     * @param array $params Lower-case query parameters
     * @param array $result Partial result (UTM values)
     * @param string|null $referrer Raw referrer
     * @param string|null $referrerHost Referrer host
     * @param bool $isInternal Referrer is the shop itself
     * @return string Source code
     */
    protected static function detectSource(array $params, array $result, $referrer, $referrerHost, $isInternal)
    {
        // Click IDs added by ad platforms are the most reliable signal
        if (isset($params['gclid']) || isset($params['gbraid']) || isset($params['wbraid']) || isset($params['gad_source'])) {
            return self::GOOGLE_ADS;
        }
        if (isset($params['fbclid'])) {
            return self::META;
        }
        if (isset($params['msclkid'])) {
            return self::SEARCH;
        }

        if ($result['utm_source'] !== null) {
            $source = Tools::strtolower($result['utm_source']);
            $medium = Tools::strtolower((string) $result['utm_medium']);

            if (strpos($source, 'google') !== false && in_array($medium, array('cpc', 'ppc', 'paid', 'paidsearch', 'paid_search', 'sem'), true)) {
                return self::GOOGLE_ADS;
            }
            if (preg_match('/^(fb|ig|meta)$|facebook|instagram/', $source)) {
                return self::META;
            }
            if ($medium === 'email' || preg_match('/newsletter|mailchimp|brevo|sendinblue|mail/', $source)) {
                return self::EMAIL;
            }

            return self::CAMPAIGN;
        }

        // Added by Google to organic results and free Shopping listings
        if (isset($params['srsltid'])) {
            return self::GOOGLE_ORGANIC;
        }

        if ($referrerHost !== null) {
            if ($isInternal) {
                return self::INTERNAL;
            }

            return self::classifyReferrerHost($referrerHost);
        }

        // Empty referrer from the browser means direct traffic; null means not recorded
        return $referrer === null ? self::UNKNOWN : self::DIRECT;
    }

    /**
     * @param string $host Lower-case external referrer host
     * @return string Source code
     */
    protected static function classifyReferrerHost($host)
    {
        if (preg_match('/(^|\.)mail\.google\.|(^|\.)outlook\.(live|office)\.com$|(^|\.)mail\.yahoo\./', $host)) {
            return self::EMAIL;
        }
        if (preg_match('/(^|\.)google\.[a-z.]+$/', $host)) {
            return self::GOOGLE_ORGANIC;
        }
        if (preg_match('/(^|\.)(bing|yahoo|duckduckgo|ecosia|yandex|baidu|qwant|startpage)\.[a-z.]+$/', $host)) {
            return self::SEARCH;
        }
        if (preg_match('/(^|\.)(facebook|fb|instagram|messenger)\.(com|me)$/', $host)) {
            return self::META;
        }
        if (preg_match('/(^|\.)(linkedin|twitter|x|t|pinterest|tiktok|youtube|reddit|threads)\.(com|co|net|it)$|(^|\.)lnkd\.in$|(^|\.)pin\.it$/', $host)) {
            return self::SOCIAL;
        }

        return self::REFERRAL;
    }

    /**
     * @param string $url URL
     * @return array Query parameters with lower-case keys
     */
    protected static function getQueryParams($url)
    {
        $query = parse_url((string) $url, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return array();
        }

        $params = array();
        parse_str($query, $params);

        return array_change_key_case($params, CASE_LOWER);
    }

    /**
     * @param array $params Query parameters
     * @param string $name Parameter name
     * @param int $maxLength Maximum length
     * @return string|null
     */
    protected static function cleanParam(array $params, $name, $maxLength)
    {
        if (!isset($params[$name]) || !is_string($params[$name])) {
            return null;
        }

        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($params[$name])));
        if ($value === '') {
            return null;
        }

        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : Tools::substr($value, 0, $maxLength);
    }
}
