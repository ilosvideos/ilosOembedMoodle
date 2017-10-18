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
 * @copyright 2012 Matthew Cannings; modified 2015 by Microsoft, Inc.; modified 2016 Ilos Co
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * code based on the following filters...
 */

defined('MOODLE_INTERNAL') || die();

require_once('env.php');

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

        $hasPerformanceShortcut = $this->hasPerformanceShortcut($text);

        if($hasPerformanceShortcut === true)
        {
            return $text;
        }

        return $this->doFilter($text);
    }

    /**
     * @param $text
     * @return mixed
     * @throws dml_exception
     */
    private function doFilter($text)
    {
        $filteredText = $text;

        if (get_config('filter_ilos_oembed', 'ilos')) {
            //Filter all anchor tags with an ilos pattern
            $search = '/<a\s[^>]*href="(https?:\/\/(www\.|'.ILOS_HOST.'\.)?)(ilos\.video|ilosvideos\.com\/view)\/(.*?)"(.*?)>(.*?)<\/a>/is';
            $filteredText = preg_replace_callback($search, array(&$this, 'filterOembedIlosCallback'), $filteredText);
        }

        if (empty($filteredText) or $filteredText === $text) {
            // Error or not filtered.
            unset($filteredText);
            return $text;
        }

        return $filteredText;
    }

    /**
     * @param $text
     * @return bool
     */
    private function hasPerformanceShortcut($text)
    {
        if (!is_string($text) or empty($text)) {
            // Non string data can not be filtered anyway.
            return true;
        }

        if (stripos($text, '</a>') === false) {
            // Performance shortcut - all regexes below end with the </a> tag.
            // If not present nothing can match.
            return true;
        }
    }

    /**
     * @param $link
     * @return bool|string
     */
    private function filterOembedIlosCallback($link) {
        $clickableLink = $this->isLinkClickable($link);

        $isButton = $this->isButton($link);

        if($isButton !== false)
        {
            return $isButton;
        }
        $url = trim($link[1]).trim($link[3]).'/'.trim($link[4]);
        $json = $this->curlCall($url);

        $error = $this->handleErrors($json);
        if($error === false)
        {
            $embedCode = $this->getEmbedCode($json);
            return $embedCode;
        }

        return $error;
    }

    /**
     * @param $link
     * @return bool
     * This is to prevent the styled record button link to remain styled, we know the new button will have a class ilos-button-handle
     */
    private function isButton($link) {
        if(strpos($link[5], 'ilos-button-handle') > 0){
            return $link[0];
        }

        //fallback to old way of doing it (old color)
        if(strpos($link[5], 'background-color:#F94E4E') > 0){
            return $link[0];
        }

        //fallback to old way of doing it (new color)
        if(strpos($link[5], 'background-color:#F72B2B') > 0){
            return $link[0];
        }

        return false;
    }

    /**
     * In moodle there are two ways to add a link. Link and Media Link, if we use the Link option it should show just
     * the link. Note: if you modify the link with the HTML editor this might not longer return the same result
     * @param $link
     * @return bool
     */
    private function isLinkClickable($link)
    {
        //$link[6] is the text inside the <a></a> tags. <a>example</a> returns example
        //$link[0] is the original <a></a> tag
        if($link[6] == "")
        {
            return $link[0];
        }

        $originalLink = $link[1].$link[3]."/".$link[4];

        $isBlankTarget = trim($link[5]) == 'class="_blanktarget"';

        if(!$isBlankTarget && $link[6] == $originalLink) {
            return $link[0];
        }

        return false;
    }

    /**
     * Makes the OEmbed request to the service that supports the protocol.
     *
     * @param $url
     * @return mixed|null|string The HTTP response object from the OEmbed request.
     */
    private function curlCall($url) {

        $url = "https://".ILOS_HOST.".ilosvideos.com/oembed?url=".$url."&format=json";

        $curl = new \curl();
        $ret = $curl->get($url);

        // Check if curl call fails.
        if ($curl->errno != CURLE_OK) {
            return null;
        }

        $result = json_decode($ret, true);
        return $result;
    }

    /**
     * Handles if the oembed service returned any error. For instance: You don't have permission to see the video
     * @param $json
     * @return bool|string
     */
    private function handleErrors($json)
    {
        if ($json === null) {
            return '<h3>'. get_string('connection_error', 'filter_ilos_oembed') .'</h3>';
        }

        if(!is_array($json) && preg_match('#^404|401|501#', $json)) {
            return "Resource could not be displayed: ".$json;
        }

        return false;
    }

    /**
     * Return the HTML content to be embedded given the response from the OEmbed request.
     * It returns the embeddable HTML from the OEmbed request.
     *
     * @param array $json Response object returned from the OEmbed request.
     * @return string The HTML content to be embedded in the page.
     */
    private function getEmbedCode($json) {

        return $json['html'];
    }

}