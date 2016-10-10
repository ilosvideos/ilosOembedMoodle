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
 * Filter for component 'filter_ilos_oembed'
 *
 * @package   filter_ilos_oembed
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
 * @package    filter_ilos_oembed
 */
class filter_ilos_oembed extends moodle_text_filter {

    /**
     * Set up the filter using settings provided in the admin settings page.
     *
     * @param $page
     * @param $context
     */
    public function setup($page, $context) {
        // This only requires execution once per request.
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

        if (!is_string($text) or empty($text)) {
            // Non string data can not be filtered anyway.
            return $text;
        }

        if (stripos($text, '</a>') === false) {
            // Performance shortcut - all regexes below end with the </a> tag.
            // If not present nothing can match.
            return $text;
        }

        $newtext = $text; // We need to return the original value if regex fails!

        if (get_config('filter_ilos_oembed', 'ilos')) {
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.|'.ILOS_HOST.'\.)?)(ilos\.video|ilosvideos\.com\/view)\/(.*?)"(.*?)>(.*?)<\/a>/is';
            $newtext = preg_replace_callback($search, array(&$this, 'filter_ilos_oembed_iloscallback'), $newtext);
        }

        if (empty($newtext) or $newtext === $text) {
            // Error or not filtered.
            unset($newtext);
            return $text;
        }

        return $newtext;
    }

    /**
     * @param $link
     * @return bool|string
     */
    private function filter_ilos_oembed_iloscallback($link) {
        //global $CFG;
        $url = "https://".ILOS_HOST.".ilosvideos.com/oembed?url=".trim($link[1]).trim($link[3]).'/'.trim($link[4])."&format=json";
        $json = $this->filter_ilos_oembed_curlcall($url, true);

        $error = $this->filter_ilos_oembed_handle_error($json);
        if($error === false)
        {
            $embedCode = $this->filter_ilos_oembed_vidembed($json);
            return $embedCode;
        }

        return $error;
    }

    /**
     * Handles if the oembed service returned any error. For instance: You don't have permission to see the video
     * @param $json
     * @return bool|string
     */
    private function filter_ilos_oembed_handle_error($json)
    {
        //TODO maybe add link to the video?
        if (preg_match('#^404|401|501#', $json)) {
            return "Video could not be displayed: ".$json;
        }

        return false;
    }

    /**
     * Makes the OEmbed request to the service that supports the protocol.
     *
     * @param $url URL for the Oembed request
     * @return mixed|null|string The HTTP response object from the OEmbed request.
     */
    private function filter_ilos_oembed_curlcall($url, $noCache = false) {
        static $cache;

        if (!isset($cache)) {
            $cache = cache::make('filter_ilos_oembed', 'embeddata');
        }

        if (!$noCache && $ret = $cache->get(md5($url))) {
            return json_decode($ret, true);
        }

        $curl = new \curl();
        $ret = $curl->get($url);

        // Check if curl call fails.
        if ($curl->errno != CURLE_OK) {
            return null;
        }

        $cache->set(md5($url), $ret);
        $result = json_decode($ret, true);
        return $result;
    }

    /**
     * Return the HTML content to be embedded given the response from the OEmbed request.
     * It returns the embeddable HTML from the OEmbed request. An error message is returned if there was an error during
     * the request.
     *
     * @param array $json Response object returned from the OEmbed request.
     * @param string $params Additional parameters to include in the embed URL.
     * @return string The HTML content to be embedded in the page.
     */
    private function filter_ilos_oembed_vidembed($json, $params = '') {

        if ($json === null) {
            return '<h3>'. get_string('connection_error', 'filter_ilos_oembed') .'</h3>';
        }

        $embed = $json['html'];

        if ($params != ''){
            $embed = str_replace('?feature=oembed', '?feature=oembed'.htmlspecialchars($params), $embed );
        }

        return $embed;
    }

}