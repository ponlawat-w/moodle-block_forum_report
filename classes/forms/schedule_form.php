<?php

namespace block_forum_report\forms;

defined('MOODLE_INTERNAL') or die();

require_once(__DIR__ . '/../../../../lib/formslib.php');
require_once(__DIR__ . '/../../lib.php');
require_once(__DIR__ . '/../engagement.php');

class schedule_form extends \moodleform
{
    private bool $expanded;
    private int $courseid;
    private \core\context\block $contextblock;

    public function __construct(int $courseid, bool $expanded = true)
    {
        $this->courseid = $courseid;
        $contextcourse = \core\context\course::instance($this->courseid);
        $this->contextblock = block_forum_report_getblockcontext($contextcourse);
        $this->expanded = $expanded;
        parent::__construct();
    }

    //Add elements to form
    public function definition()
    {
        /** @var \moodle_database $DB; */
        global $DB, $COURSE;

        $mform = $this->_form;

        $mform->addElement('header', 'filter', get_string('requestnewreport', 'block_forum_report'));
        $mform->setExpanded('filter', $this->expanded);

        $forumdata = $DB->get_records('forum', array('course' => $COURSE->id));
        foreach ($forumdata as $forum) {
            $forums[$forum->id] = $forum->name;
        }
        $forums = array('0' => get_string('all')) + $forums;
        $select_forum = $mform->addElement('select', 'forum', get_string('forum', 'forum'), $forums);
        $select_forum->setSelected('0');

        $groupoptions = [];
        if (has_capability('block/forum_report:viewothergroups', $this->contextblock)) {
            $allgroups = groups_get_all_groups($COURSE->id);
            if (count($allgroups)) {
                $groupoptions[0] = get_string('allgroups');
                foreach ($allgroups as $group) {
                    $groupoptions[$group->id] = $group->name;
                }
            }
        } else {
            $mygroups = groups_get_user_groups($this->courseid);
            $groupoptions[0] = get_string('allmygroups', 'block_forum_report');
            foreach ($mygroups[0] as $mygroupid) {
                $groupoptions[$mygroupid] = groups_get_group_name($mygroupid);
            }
        }
        $select_group = $mform->addElement('select', 'group', get_string('group'), $groupoptions);
        $select_group->setSelected(count($groupoptions) > 0 ? array_keys($groupoptions)[0] : null);

        $countrychoices = get_string_manager()->get_list_of_countries();
        $countrychoices = array('0' => get_string('all')) + $countrychoices;
        $select_country = $mform->addElement('select', 'country', get_string('country'), $countrychoices);
        $select_country->setSelected('0');
        $mform->addElement('hidden', 'course', $this->courseid);
        $mform->setType('course', PARAM_INT);

        // Open and close dates.
        $mform->addElement('date_time_selector', 'starttime', get_string('reportstart', 'block_forum_report'), array('optional' => true, 'startyear' => 2000, 'stopyear' => date("Y"), 'step' => 5));
        $mform->setDefault('starttime', null);
        $mform->addElement('date_time_selector', 'endtime', get_string('reportend', 'block_forum_report'), array('optional' => true, 'startyear' => 2000, 'stopyear' => date("Y"), 'step' => 5));
        $mform->setDefault('endtime', null);

        \block_forum_report\engagement::addtoform($mform);

        if (has_capability('block/forum_report:getinstantreport', $this->contextblock)) {
            $mform->addElement('checkbox', 'instant', get_string('getinstantreport', 'block_forum_report'));
            $mform->setDefault('instant', false);
        }

        $mform->addElement('submit', 'submit', get_string('submit'));
    }
}
