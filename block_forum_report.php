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

require_once($CFG->libdir . '/navigationlib.php');

defined('MOODLE_INTERNAL') || die();

class block_forum_report extends block_base
{
    function init()
    {
        $this->title = get_string('pluginname', 'block_forum_report');
    }

    public function has_config()
    {
        return true;
    }

    function applicable_formats()
    {
        return array('course' => true);
    }

    function instance_config_save($data, $nolongerused = false)
    {
        parent::instance_config_save($data);
    }

    function get_content()
    {
        global $CFG, $DB, $OUTPUT, $USER, $COURSE;
        $context = $this->page->context;

        // Removed flat navigation as it is deprecated since Moodle 4

        if ($this->content !== NULL) {
            return $this->content;
        }
        $this->content = new stdClass;
        $this->content->items = array();
        $this->content->icons = array();
        $this->content->footer = '';

        $course = $this->page->course;
        $context = $this->page->context;
        if (!has_capability('block/forum_report:view', $context)) {
            return;
        }

        $url = new \moodle_url('/blocks/forum_report/schedule.php', ['course' => $course->id]);
        $this->content->text = html_writer::link($url, get_string('showreport', 'block_forum_report'), ['class' => 'btn btn-primary']);
        return $this->content;
    }

    function instance_allow_multiple()
    {
        return false;
    }
}
