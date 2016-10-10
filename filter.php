<?php
// This file is part of Moodle-oembed-Filter
//
// Moodle-oembed-Filter is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle-oembed-Filter is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle-oembed-Filter.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Filter for component 'filter_oembed'
 *
 * @package   filter_oembed
 * @copyright 2012 Matthew Cannings; modified 2015 by Microsoft, Inc.
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * code based on the following filters...
 */

define("ILOS_HOST", "cloud");

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
/**
 * Filter for processing HTML content containing links to media from services that support the OEmbed protocol.
 * The filter replaces the links with the embeddable content returned from the service via the Oembed protocol.
 *
 * @package    filter_oembed
 */
class filter_oembed extends moodle_text_filter {

    /**
     * Set up the filter using settings provided in the admin settings page.
     *
     * @param $page
     * @param $context
     */
    public function setup($page, $context) {
        // This only requires execution once per request.
        static $jsinitialised = false;
        if (get_config('filter_oembed', 'lazyload')) {
            if (empty($jsinitialised)) {
                $page->requires->yui_module(
                        'moodle-filter_oembed-lazyload',
                        'M.filter_oembed.init_filter_lazyload',
                        array(array('courseid' => 0)));
                $jsinitialised = true;
            }
        }
        if (get_config('filter_oembed', 'provider_powerbi_enabled')) {
            global $PAGE;
            $PAGE->requires->yui_module('moodle-filter_oembed-powerbiloader', 'M.filter_oembed.init_powerbiloader');
        }
    }

    /**
     * Filters the given HTML text, looking for links pointing to media from services that support the Oembed
     * protocol and replacing them with the embeddable content returned from the protocol.
     *
     * @param $text HTML to be processed.
     * @param $options
     * @return string String containing processed HTML.
     */
    public function filter($text, array $options = array()) {
        global $CFG;

        if (!is_string($text) or empty($text)) {
            // Non string data can not be filtered anyway.
            return $text;
        }
        // if (get_user_device_type() !== 'default'){
            // no lazy video on mobile
            // return $text;

        // }
        if (stripos($text, '</a>') === false) {
            // Performance shortcut - all regexes below end with the </a> tag.
            // If not present nothing can match.
            return $text;
        }

        $newtext = $text; // We need to return the original value if regex fails!

        if (get_config('filter_oembed', 'ilos')) {
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.|'.ILOS_HOST.'\.)?)(ilos\.video|ilosvideos\.com\/view)\/(.*?)"(.*?)>(.*?)<\/a>/is';
            $newtext = preg_replace_callback($search, 'filter_oembed_iloscallback', $newtext);
        }

        // New method for embed providers.
        $providers = static::get_supported_providers();
        $filterconfig = get_config('filter_oembed');
        foreach ($providers as $provider) {
            $enabledkey = 'provider_'.$provider.'_enabled';
            if (!empty($filterconfig->$enabledkey)) {
                $providerclass = '\filter_oembed\provider\\'.$provider;
                if (class_exists($providerclass)) {
                    $provider = new $providerclass();
                    $newtext = $provider->filter($newtext);
                }
            }
        }

        if (empty($newtext) or $newtext === $text) {
            // Error or not filtered.
            unset($newtext);
            return $text;
        }

        return $newtext;
    }

    /**
     * Return list of supported providers.
     *
     * @return array Array of supported providers.
     */
    public static function get_supported_providers() {
        return [
//            'docsdotcom', 'powerbi', 'officeforms'
        ];
    }
}

/**
 * Looks for links pointing to ilos content and processes them.
 *
 * @param $link HTML tag containing a link
 * @return string HTML content after processing.
 */
function filter_oembed_iloscallback($link) {
    global $CFG;
    $url = "https://".ILOS_HOST.".ilosvideos.com/oembed?url=".trim($link[1]).trim($link[3]).'/'.trim($link[4])."&format=json'";
    $json = filter_oembed_curlcall($url, true);

    //no html code (something went wrong)
    if(!$json["html"])
    {
        return "Something went wrong:".$json;
    }

    return filter_oembed_vidembed($json);
}

/**
 * Makes the OEmbed request to the service that supports the protocol.
 *
 * @param $url URL for the Oembed request
 * @return mixed|null|string The HTTP response object from the OEmbed request.
 */
function filter_oembed_curlcall($url, $noCache = false) {
   static $cache;

    if (!isset($cache)) {
        $cache = cache::make('filter_oembed', 'embeddata');
    }

    if (!$noCache && $ret = $cache->get(md5($url))) {
        return json_decode($ret, true);
    }

    $curl = new \curl();
    $ret = $curl->get($url);

    // Check if curl call fails.
    if ($curl->errno != CURLE_OK) {
        // Check if error is due to network connection.
        if (in_array($curl->errno, [6, 7, 28])) {
            // Try curl call up to 3 times.
            usleep(50000);
            $retryno = (!is_int($retryno)) ? 0 : $retryno+1;
            if ($retryno < 3) {
                return $this->getoembeddata($url, $retryno);
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    $cache->set(md5($url), $ret);
    $result = json_decode($ret, true);
    return $result;
}

/**
 * Return the HTML content to be embedded given the response from the OEmbed request.
 * This method returns the thumbnail image if we lazy loading is enabled. Ogtherwise it returns the
 * embeddable HTML returned from the OEmbed request. An error message is returned if there was an error during
 * the request.
 *
 * @param array $json Response object returned from the OEmbed request.
 * @param string $params Additional parameters to include in the embed URL.
 * @return string The HTML content to be embedded in the page.
 */
function filter_oembed_vidembed($json, $params = '') {

    if ($json === null) {
        return '<h3>'. get_string('connection_error', 'filter_oembed') .'</h3>';
    }

    $embed = $json['html'];

    if ($params != ''){
        $embed = str_replace('?feature=oembed', '?feature=oembed'.htmlspecialchars($params), $embed );
    }

    if (get_config('filter_oembed', 'lazyload')) {
        $embed = htmlspecialchars($embed);
        $dom = new DOMDocument();

        // To surpress the loadHTML Warnings.
        libxml_use_internal_errors(true);
        $dom->loadHTML($json['html']);
        libxml_use_internal_errors(false);

        // Get height and width of iframe.
        $height = $dom->getElementsByTagName('iframe')->item(0)->getAttribute('height');
        $width = $dom->getElementsByTagName('iframe')->item(0)->getAttribute('width');

        $embedcode = '<a class="lvoembed lvvideo" data-embed="'.$embed.'"';
        $embedcode .= 'href="#" data-height="'. $height .'" data-width="'. $width .'"><div class="filter_oembed_lazyvideo_container">';
        $embedcode .= '<img class="filter_oembed_lazyvideo_placeholder" src="'.$json['thumbnail_url'].'" />';
        $embedcode .= '<div class="filter_oembed_lazyvideo_title"><div class="filter_oembed_lazyvideo_text">'.$json['title'].'</div></div>';
        $embedcode .= '<span class="filter_oembed_lazyvideo_playbutton"></span>';
        $embedcode .= '</div></a>';
    } else {
        $embedcode = $embed;
    }

    return $embedcode;
}
