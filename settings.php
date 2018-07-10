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
 * Filter for component 'filter_vidgrid_oembed'
 *
 * @package   filter_vidgrid_oembed
 * @copyright 2012 Matthew Cannings, Sandwell College; modified 2015 by Microsoft, Inc.; modified 2018 VidGrid
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * code based on the following filters...
 * Screencast (Mark Schall)
 * Soundcloud (Troy Williams)
 */

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__.'/filter.php');

if ($ADMIN->fulltree) {
    $torf = array('1' => new lang_string('yes'), '0' => new lang_string('no'));
    $item = new admin_setting_configselect('filter_vidgrid_oembed/vidgrid', new lang_string('vidgrid', 'filter_vidgrid_oembed'), '', 1, $torf);
    $settings->add($item);
}
