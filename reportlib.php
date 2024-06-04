<?php

require_once(__DIR__ . '/../../lib/formslib.php');
require_once(__DIR__ . '/classes/engagement.php');

class report_form extends moodleform
{
    private int $courseid;
    private \core\context\block $contextblock;

    public function __construct($courseid)
    {
        /** @var \moodle_database $DB */
        global $DB;
        $this->courseid = $courseid;
        $contextcourse = \core\context\course::instance($this->courseid);
        $block = $DB->get_record('block_instances', ['blockname' => 'forum_report', 'parentcontextid' => $contextcourse->id], '*', MUST_EXIST);
        $this->contextblock = \core\context\block::instance($block->id);
        parent::__construct();
    }

    //Add elements to form
    public function definition()
    {
        /** @var \moodle_database $DB; */
        global $CFG, $DB, $COURSE;
        $perpage = optional_param('perpage', 0, PARAM_RAW);
        $countryid = optional_param('country', '', PARAM_RAW);
        $groupid = optional_param('group', 0, PARAM_INT);
        $forumid = optional_param('forum', 0, PARAM_INT);
        $start = optional_param('start', '', PARAM_RAW);
        $end = optional_param('end', '', PARAM_RAW);

        $mform = $this->_form;

        $mform->addElement('header', 'filter', get_string('reportfilter', 'block_forum_report'));
        $forumdata = $DB->get_records('forum', array('course' => $COURSE->id));
        foreach ($forumdata as $forum) {
            $forums[$forum->id] = $forum->name;
        }
        $forums = array('0' => get_string('all')) + $forums;
        $select_forum = $mform->addElement('select', 'forum', get_string('forum', 'forum'), $forums);
        $select_forum->setSelected("$forumid");

        $groupoptions = [];
        if (has_capability('block/forum_report:viewothergroups', $this->contextblock)) {
            $allgroups = groups_get_all_groups($COURSE->id);
            if (count($allgroups)) {
                $groupoptions = array('0' => get_string('allgroups'));
                foreach ($allgroups as $group) {
                    $groupoptions[$group->id] = $group->name;
                }
            }
        } else {
            $mygroups = groups_get_user_groups($this->courseid);
            foreach ($mygroups[0] as $mygroupid) {
                if (!$groupid) {
                    $groupid = $mygroupid;
                }
                $groupoptions[$mygroupid] = groups_get_group_name($mygroupid);
            }
        }
        $select_group = $mform->addElement('select', 'group', get_string('group'), $groupoptions);
        $select_group->setSelected($groupid);

        $countries = get_string_manager()->get_list_of_countries();

        $countrychoices = get_string_manager()->get_list_of_countries();
        $countrychoices = array('0' => get_string('all')) + $countrychoices;
        $select_country = $mform->addElement('select', 'country', get_string('country'), $countrychoices);
        $select_country->setSelected("$countryid");
        $mform->addElement('hidden', 'course', $COURSE->id);
        $mform->setType('course', PARAM_INT);

        // Open and close dates.
        $mform->addElement('date_time_selector', 'starttime', get_string('reportstart', 'block_forum_report'), array('optional' => true, 'startyear' => 2000, 'stopyear' => date("Y"), 'step' => 5));
        $mform->setDefault('starttime', $start);
        $mform->addElement('date_time_selector', 'endtime', get_string('reportend', 'block_forum_report'), array('optional' => true, 'startyear' => 2000, 'stopyear' => date("Y"), 'step' => 5));
        $mform->setDefault('endtime', $end);
        //BL Customization
        $Perpage = array('0' => 'All', '5' => '5', '10' => '10', '20' => '20', '30' => '30', '50' => '50', '100' => '100');
        $select = $mform->addElement('select', 'perpage', get_string('perpage', 'block_forum_report'), $Perpage);
        $select->setSelected("$perpage");

        \block_forum_report\engagement::addtoform($mform);

        //BL Customization
        $mform->addElement('submit', 'changefilter', get_string('showreport', 'block_forum_report'));
        $mform->addElement('button', 'download', get_string('download'),array('class'=>'download' ,'style'=>'background-color:#0f6fc5; color:#fff;border-color:#0a4e8a'));

    }
}

function forum_report_sort($sortby)
{
    return function ($a, $b) use ($sortby) {
        foreach ($sortby as $key => $order) {
            if (strpos($key, "name") !== FALSE) {
                if ($order == 4) {
                    $cmp = strcmp($a->$key, $b->$key);
                } else {
                    $cmp = strcmp($b->$key, $a->$key);
                }
            } else {

                if ($order == 4) {
                    return ($a->$key < $b->$key) ? -1 : 1;
                } else {
                    return ($a->$key > $b->$key) ? -1 : 1;
                }
            }
            break;
        }
        return $cmp;
    };
}
// This function set & returns mid night time (00:00:00) for given timestamp.
//BL Customized Code -->>
function get_midnight($timestamp)
{
    $beginOfDay = DateTime::createFromFormat('Y-m-d H:i:s', (new DateTime())->setTimestamp($timestamp)->format('Y-m-d 00:00:00'))->getTimestamp();
    return $beginOfDay;
}
//BL Customized Code <<--
function get_mulutimedia_num($text)
{
    global $CFG, $PAGE;

    if (!is_string($text) or empty($text)) {
        // non string data can not be filtered anyway
        return 0;
    }

    if (stripos($text, '</a>') === false && stripos($text, '</video>') === false && stripos($text, '</audio>') === false && (stripos($text, '<img') === false)) {
        // Performance shortcut - if there are no </a>, </video> or </audio> tags, nothing can match.
        return 0;
    }

    // Looking for tags.
    $matches = preg_split('/(<[^>]*>)/i', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    $count = new stdClass();
    $count->num = 0;
    $count->img = 0;
    $count->video = 0;
    $count->audio = 0;
    $count->link = 0;
    if (!$matches) {
        return 0;
    } else {
        // Regex to find media extensions in an <a> tag.
        $embedmarkers = core_media_manager::instance()->get_embeddable_markers();
        $re = '~<a\s[^>]*href="([^"]*(?:' .  $embedmarkers . ')[^"]*)"[^>]*>([^>]*)</a>~is';

        $tagname = '';
        foreach ($matches as $idx => $tag) {
            if (preg_match('/<(a|img|video|audio)\s[^>]*/', $tag, $tagmatches)) {
                $tagname = strtolower($tagmatches[1]);
                if ($tagname === "a" && preg_match($re, $tag)) {
                    $count->num++;
                    $count->link++;
                } else {
                    if ($tagname == "img") {
                        $count->img++;
                        $count->num++;
                    } else if ($tagname == "video") {
                        $count->video++;
                        $count->num++;
                    } else if ($tagname == "audio") {
                        $count->audio++;
                        $count->num++;
                    }
                }
            }
        }
    }
    return $count;
}

function block_forum_report_checkpermission($courseid, $groupid) {
    global $DB;
    $contextcourse = \core\context\course::instance($courseid);
    $block = $DB->get_record('block_instances', ['blockname' => 'forum_report', 'parentcontextid' => $contextcourse->id], '*', MUST_EXIST);
    $contextblock = \core\context\block::instance($block->id);
    require_capability('block/forum_report:view', $contextblock, null, true, 'noviewdiscussionpermission', 'forum');

    if (!$groupid) {
        require_capability('block/forum_report:viewothergroups', $contextblock);
    }

    $groups = groups_get_user_groups($courseid);
    if (in_array($groupid, $groups[0])) {
        return;
    }

    require_capability('block/forum_report:viewothergroups', $contextblock);
}
