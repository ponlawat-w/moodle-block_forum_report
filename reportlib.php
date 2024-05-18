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

function block_forum_report_getforummodcontextidlookup($courseid) {
    global $DB;
    $forumlookup = [];
    $forums = $DB->get_records('forum', ['course' => $courseid]);
    foreach ($forums as $forum) {
        $cm = get_coursemodule_from_instance('forum', $forum->id, $courseid, false, MUST_EXIST);
        $forumlookup[$forum->id] = \core\context\module::instance($cm->id)->id;
    }
    return $forumlookup;
}

/**
 * @param string $fieldname
 * @param int $starttime
 * @param int $endtime
 * @param string $prefix
 * @return string
 */
function block_forum_report_gettimecondition($fieldname, $starttime, $endtime, $prefix) {
    if ($starttime > 0 && $endtime > 0) {
        return "AND {$fieldname} BETWEEN :{$prefix}starttime AND :{$prefix}endtime";
    }
    if ($starttime > 0) {
        return "AND {$fieldname} >= :{$prefix}starttime";
    }
    if ($endtime > 0) {
        return "AND {$fieldname} <= :{$prefix}endtime";
    }
    return '';
}

/**
 * @param \core\context\course|\core\context\module $context
 * @param int $courseid
 * @param int $forumid
 * @param int $groupid
 * @param string|null|0|'0' $country
 * @param int $starttime
 * @param int $endtime
 * @return stdClass[]
 */
function block_forum_report_getbasicreports(
    $context,
    $courseid,
    $forumid = 0,
    $groupid = 0,
    $country = null,
    $starttime = 0,
    $endtime = 0
) {
    /** @var $DB \moodle_database */
    global $DB;

    $capacityjoin = get_with_capability_join($context, 'mod/forum:viewdiscussion', 'u.id');
    $params = $capacityjoin->params;

    $groupjoin = $groupid ? 'JOIN' : 'LEFT OUTER JOIN';
    $groupcondition = $groupid ? 'AND ug.id = :group' : '';
    if ($groupid) {
        $params['group'] = $groupid;
    }

    $countrycondition = (is_null($country) || !$country) ? '' : 'AND u.country = :country';
    $params['country'] = $country;

    $discussioncondition = $forumid ? 'fd.forum = :fdforum' : 'fd.course = :fdcourse';
    $params[$forumid ? 'fdforum' : 'fdcourse'] = $forumid ? $forumid : $courseid;

    $posttimecondition = block_forum_report_gettimecondition('fp.created', $starttime, $endtime, 'fp');
    if ($starttime > 0) $params['fpstarttime'] = $starttime;
    if ($endtime > 0) $params['fpendtime'] = $endtime;

    $logcondition = $forumid ? 'lsl.contextinstanceid = :contextinstanceid AND lsl.contextlevel = :contextlevel' : 'lsl.courseid = :lslcourse';
    if ($forumid) {
        $params['contextinstanceid'] = $context->instanceid;
        $params['contextlevel'] = $context::LEVEL;
    } else {
        $params['lslcourse'] = $courseid;
    }

    $logtimecondition = block_forum_report_gettimecondition('lsl.timecreated', $starttime, $endtime, 'lsl');
    if ($starttime > 0) $params['lslstarttime'] = $starttime;
    if ($endtime > 0) $params['lslendtime'] = $endtime;

    $selectgroupsql = $DB->get_dbfamily() === 'postgres' ?
        "array_to_string(array_agg(g.groupname), ',') groupnames"
        : "GROUP_CONCAT(g.groupname SEPARATOR ',') groupnames";

    $sql = <<<SQL
        SELECT
            t1.id, username, firstname, lastname, groupnames, country, institution,
            posts, replies, unique_activedays, firstpost, lastpost,
            viewscount, uniqueviewdays
        FROM (
            SELECT
                u.id,
                username,
                firstname,
                lastname,
                {$selectgroupsql},
                country,
                institution
            FROM {user} u
                {$capacityjoin->joins}
                {$groupjoin} (
                    SELECT ug.id id, ug.name groupname, gm.userid userid
                    FROM {groups_members} gm
                        JOIN {groups} ug ON gm.groupid = ug.id
                    WHERE ug.courseid = :ugcourse {$groupcondition}
                ) g ON g.userid = u.id
            WHERE {$capacityjoin->wheres} {$countrycondition}
            GROUP BY u.id
        ) t1 LEFT OUTER JOIN (
            SELECT
                u.id,
                SUM(CASE WHEN fp.parent = 0 THEN 1 ELSE 0 END) posts,
                SUM(CASE WHEN fp.parent != 0 THEN 1 ELSE 0 END) replies,
                COUNT(DISTINCT FLOOR(fp.created / 86400)) unique_activedays,
                MIN(fp.created) firstpost,
                MAX(fp.created) lastpost
            FROM {user} u
                LEFT OUTER JOIN {forum_posts} fp
                    ON fp.userid = u.id
            WHERE fp.discussion IN (
                SELECT fd.id FROM {forum_discussions} fd
                WHERE {$discussioncondition}
            ) {$posttimecondition}
            GROUP BY u.id
        ) t2 ON t1.id = t2.id LEFT OUTER JOIN (
            SELECT
                u.id,
                COUNT(DISTINCT lsl.id) viewscount,
                COUNT(DISTINCT FLOOR(lsl.timecreated)) uniqueviewdays
            FROM {user} u
                LEFT OUTER JOIN {logstore_standard_log} lsl
                    ON lsl.userid = u.id
            WHERE lsl.eventname = '\\mod_forum\\event\\discussion_viewed'
                AND {$logcondition}
                {$logtimecondition}
            GROUP BY u.id
        ) t3 ON t2.id = t3.id;
    SQL;

    $params['ugcourse'] = $courseid;

    return $DB->get_records_sql($sql, $params);
}

function block_forum_report_countattachmentmultimedia($modcontextid, $postid) {
    $count = new stdClass();
    $count->num = 0;
    $count->img = 0;
    $count->video = 0;
    $count->audio = 0;
    $count->link = 0;

    $fs = get_file_storage();
    $files = $fs->get_area_files($modcontextid, 'mod_forum', 'attachment', $postid);
    foreach ($files as $file) {
        $mimetype = $file->get_mimetype();
        if (substr($mimetype, 0, 6) == 'image/') {
            $count->num++;
            $count->img++;
        } else if (substr($mimetype, 0, 6) == 'video/') {
            $count->num++;
            $count->video++;
        } else if (substr($mimetype, 0, 6) == 'audio/') {
            $count->num++;
            $count->audio++;
        }
    }

    return $count;
}

function block_forum_report_reactforuminstalled() {
    $pluginmanager = core_plugin_manager::instance();
    return isset($pluginmanager->get_installed_plugins('local')['reactforum']);
}

function block_forum_report_getreactionsgiven($userid, $courseid, $forumid, $starttime, $endtime) {
    /** @var \moodle_database $DB */
    global $DB;
    $timecondition = block_forum_report_gettimecondition('fp.created', $starttime, $endtime, '');
    $sql = <<<SQL
        SELECT COUNT(rr.id) reactionsgiven
            FROM {reactforum_reacted} rr
                JOIN {forum_posts} fp ON rr.post = fp.id
                JOIN {forum_discussions} fd ON fp.discussion = fd.id
            WHERE rr.userid = :userid
                AND ((:forumid1 = 0 AND fd.course = :courseid) OR fd.forum = :forumid2)
                {$timecondition}
    SQL;
    $params = [
        'userid' => $userid,
        'courseid' => $courseid,
        'forumid1' => $forumid,
        'forumid2' => $forumid
    ];
    if ($starttime) $params['starttime'] = $starttime;
    if ($endtime) $params['endtime'] = $endtime;
    return $DB->get_record_sql($sql, $params)->reactionsgiven;
}

function block_forum_report_getreactionsreceived($userid, $courseid, $forumid, $starttime, $endtime) {
    /** @var \moodle_database $DB */
    global $DB;
    $timecondition = block_forum_report_gettimecondition('fp.created', $starttime, $endtime, '');
    $sql = <<<SQL
        SELECT COUNT(rr.id) received
            FROM {reactforum_reacted} rr
            JOIN {forum_posts} fp ON rr.post = fp.id
            JOIN {forum_discussions} fd ON fp.discussion = fd.id
            WHERE fp.userid = :userid
                AND ((:forumid1 = 0 AND fd.course = :courseid) OR fd.forum = :forumid2)
                {$timecondition}
    SQL;
    $params = [
        'userid' => $userid,
        'courseid' => $courseid,
        'forumid1' => $forumid,
        'forumid2' => $forumid
    ];
    if ($starttime) $params['starttime'] = $starttime;
    if ($endtime) $params['endtime'] = $endtime;
    return $DB->get_record_sql($sql, $params)->received;
}

/**
 * @param \core\context\module[] $modcontextidlookup
 * @param int $userid
 * @param int $courseid
 * @param int $forumid
 * @param int $starttime
 * @param int $endtime
 * @return stdClass
 */
function block_forum_report_countwordmultimedia(
    $modcontextidlookup,
    $userid,
    $courseid,
    $forumid,
    $starttime,
    $endtime
) {
    /** @var \moodle_database $DB */
    global $DB;
    $result = new stdClass();
    $result->wordcount = 0;
    $result->multimedia = 0;
    $result->multimedia_image = 0;
    $result->multimedia_video = 0;
    $result->multimedia_audio = 0;
    $result->multimedia_link = 0;

    $timecondition = block_forum_report_gettimecondition('fp.created', $starttime, $endtime, '');
    $sql = <<<SQL
        SELECT fp.*, fd.forum
            FROM {forum_posts} fp
            JOIN {forum_discussions} fd ON fp.discussion = fd.id
            JOIN {context} c ON c.contextlevel = :contextlevel AND c.instanceid = fd.forum
            WHERE fp.userid = :userid
                AND ((:forumid1 = 0 AND fd.course = :courseid) OR fd.forum = :forumid2)
                {$timecondition}
    SQL;
    $params = [
        'userid' => $userid,
        'courseid' => $courseid,
        'forumid1' => $forumid,
        'forumid2' => $forumid,
        'contextlevel' => \core\context\module::LEVEL
    ];
    if ($starttime) $params['starttime'] = $starttime;
    if ($endtime) $params['endtime'] = $endtime;
    $posts = $DB->get_records_sql($sql, $params);
    foreach ($posts as $post) {
        $multimedia = get_mulutimedia_num($post->message);
        $attachment = block_forum_report_countattachmentmultimedia($modcontextidlookup[$post->forum], $post->id);

        $result->wordcount += count_words($post->message);
        $result->multimedia += ($multimedia ? $multimedia->num : 0) + $attachment->num;
        $result->multimedia_image += ($multimedia ? $multimedia->img : 0) + $attachment->img;
        $result->multimedia_video += ($multimedia ? $multimedia->video : 0) + $attachment->video;
        $result->multimedia_audio += ($multimedia ? $multimedia->audio : 0) + $attachment->audio;
        $result->multimedia_link += ($multimedia ? $multimedia->link : 0) + $attachment->link;
    }

    $result->wordcount = $result->wordcount > 0 ? $result->wordcount : '';
    $result->multimedia = $result->multimedia > 0 ? $result->multimedia : '';
    $result->multimedia_image = $result->multimedia_image > 0 ? $result->multimedia_image : '';
    $result->multimedia_video = $result->multimedia_video > 0 ? $result->multimedia_video : '';
    $result->multimedia_audio = $result->multimedia_audio > 0 ? $result->multimedia_audio : '';
    $result->multimedia_link = $result->multimedia_link > 0 ? $result->multimedia_link : '';

    return $result;
}
