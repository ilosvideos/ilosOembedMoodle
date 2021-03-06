<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Unit tests for the filter_vidgrid_oembed.
 *
 * @package    filter_vidgrid_oembed
 * @author Sushant Gawali (sushant@introp.net)
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Microsoft, Inc.
 * @copyright ilos 2017
 * @copyright VidGrid 2018 - 2020
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/filter/vidgrid-oembed/filter.php');

/**
 * @group filter_vidgrid_oembed
 * @group office365
 */
class filter_vidgrid_oembed_testcase extends basic_testcase {

    protected $filter;

    /**
     * Sets up the test cases.
     */
    protected function setUp() {
        parent::setUp();
        $this->filter = new filter_vidgrid_oembed(context_system::instance(), array());
    }

    /**
     * Performs unit tests for all services supported by the filter.
     *
     * Need to update this test to not contact external services.
     */
    public function test_filter() {
        return true;
        $vidgrid = '<p><a href="https://app.vidgrid.com/view/nacfQdQSXNUD">VidGrid</a></p>';

        $filterInput = $vidgrid;

        $filterOutput = $this->filter->filter($filterInput);

        $vidgridOutput = '<iframe width="640" height="360" allowTransparency="true" mozallowfullscreen webkitallowfullscreen allowfullscreen style="background-color:transparent;" frameBorder="0" src="https://app.vidgrid.com/embed/nacfQdQSXNUD"></iframe>';
        $this->assertContains($vidgridOutput, $filterOutput, 'vidgrid filter fails');

    }
}
