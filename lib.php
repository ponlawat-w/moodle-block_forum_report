<?php

require_once(__DIR__ . '/classes/engagement.php');

use block_forum_report\engagement;
use block_forum_report\engagementresult;

const BLOCK_FORUM_REPORT_STATUS_SCHEDULED = 0;
const BLOCK_FORUM_REPORT_STATUS_EXECUTING = 1;
const BLOCK_FORUM_REPORT_STATUS_ERROR = 2;
const BLOCK_FORUM_REPORT_STATUS_FINISH = 3;
const BLOCK_FORUM_REPORT_STATUS_MANUAL = 4;

/**
 * @var \moodle_database $DB
 */

function block_forum_report_removeschedule(int $scheduleid) {
    global $DB;

    $DB->delete_records('forum_report_results', ['schedule' => $scheduleid]);
    $DB->delete_records('forum_report_schedules', ['id' => $scheduleid]);
}

function block_forum_report_removeexistingschedule(int $userid = 0) {
    global $DB, $USER;
    $userid = $userid ? $userid : $USER->id;

    $schedules = $DB->get_records('forum_report_schedules', ['userid' => $userid, 'status' => BLOCK_FORUM_REPORT_STATUS_SCHEDULED], '', 'id');
    foreach ($schedules as $schedule) {
        block_forum_report_removeschedule($schedule->id);
    }
}

function block_forum_report_addschedule(stdClass $formdata, int $userid = 0) {
    global $DB, $USER;

    $schedule = new stdClass();
    $schedule->userid = $userid ? $userid : $USER->id;
    $schedule->createdtime = time();
    $schedule->status = BLOCK_FORUM_REPORT_STATUS_SCHEDULED;
    $schedule->course = $formdata->course;
    $schedule->country = $formdata->country ? $formdata->country : null;
    $schedule->groupid = $formdata->group ? $formdata->group : null;
    $schedule->forum = $formdata->forum ? $formdata->forum : null;
    $schedule->starttime = $formdata->starttime ? $formdata->starttime : null;
    $schedule->endtime = $formdata->endtime ? $formdata->endtime : null;
    $schedule->engagementmethod = $formdata->engagementmethod ? $formdata->engagementmethod : null;
    $schedule->engagementinternational = $formdata->engagementinternational ? $formdata->engagementinternational : null;

    return $DB->insert_record('forum_report_schedules', $schedule);
}

function block_forum_report_getnextscheduledtime() {
    $lastexecution = get_config('block_forum_report', 'lastexecution');
    if (!$lastexecution) return time() - 1;

    $lastexecutionh = date('G', $lastexecution);
    $hrs = explode(',', get_config('block_forum_report', 'executionschedule'));
    if (!count($hrs)) return null;

    foreach ($hrs as $hr) {
        if (!is_numeric($hr)) continue;
        if ($lastexecutionh >= $hr) continue;
        return mktime($hr, 0, 0, date('n', $lastexecution), date('j', $lastexecution), date('Y', $lastexecution));
    }
    if (!is_numeric($hrs[0])) return null;

    return mktime($hrs[0] + 24, 0, 0, date('n', $lastexecution), date('j', $lastexecution), date('Y', $lastexecution));
}

function block_forum_report_getstatus(int $status) {
    if ($status == BLOCK_FORUM_REPORT_STATUS_SCHEDULED) return [get_string('status_scheduled', 'block_forum_report'), ''];
    if ($status == BLOCK_FORUM_REPORT_STATUS_EXECUTING) return [get_string('status_executing', 'block_forum_report'), 'text-primary'];
    if ($status == BLOCK_FORUM_REPORT_STATUS_ERROR) return [get_string('status_error', 'block_forum_report'), 'text-danger'];
    if ($status == BLOCK_FORUM_REPORT_STATUS_FINISH) return [get_string('status_finish', 'block_forum_report'), 'text-success'];
    if ($status == BLOCK_FORUM_REPORT_STATUS_MANUAL) return [get_string('status_manual', 'block_forum_report'), 'text-secondary'];
    return ['', ''];
}

function block_forum_report_getscheduledtime(stdClass $schedule) {
    return $schedule->status == BLOCK_FORUM_REPORT_STATUS_SCHEDULED ? block_forum_report_getnextscheduledtime() : $schedule->processedtime;
}

function block_forum_report_getdownloadurl(stdClass $schedule) {
    if ($schedule->status != BLOCK_FORUM_REPORT_STATUS_FINISH) return null;
    return new moodle_url('/blocks/forum_report/view.php', ['id' => $schedule->id, 'action' => 'download']);
}

function block_forum_report_getdeleteurl(stdClass $schedule) {
    return new moodle_url('/blocks/forum_report/schedule.php', ['sid' => $schedule->id, 'action' => 'delete']);
}

function block_forum_report_getreportscontext(int $userid) {
    global $DB;
    $reports = [];

    $records = $DB->get_records('forum_report_schedules', ['userid' => $userid], 'createdtime');
    foreach ($records as $record) {
        $scheduledtime = block_forum_report_getscheduledtime($record);

        $report = [];
        $report['requestedtime'] = userdate($record->createdtime, get_string('strftimedaydatetime', 'langconfig'));
        $report['scheduledtime'] = $scheduledtime ? userdate($scheduledtime, get_string('strftimedaydatetime', 'langconfig')) : '-';
        $report['status'] = 0;
        $report['statusClass'] = '';
        $report['viewurl'] = new moodle_url('/blocks/forum_report/view.php', ['id' => $record->id]);
        $report['downloadurl'] = block_forum_report_getdownloadurl($record);
        $report['deleteurl'] = block_forum_report_getdeleteurl($record);

        $status = block_forum_report_getstatus($record->status);
        $report['status'] = $status[0];
        $report['statusClass'] = $status[1];

        $reports[] = $report;
    }

    return ['reports' => $reports];
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

function block_forum_report_getschedulecontext(stdClass $schedule) {
    if ($schedule->forum) {
        $coursemodule = get_coursemodule_from_instance('forum', $schedule->forum, $schedule->course, false, MUST_EXIST);
        return \core\context\module::instance($coursemodule->id);
    }
    return \core\context\course::instance($schedule->course);
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
    $params['lsleventname'] = '\mod_forum\event\discussion_viewed';
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
                COUNT(DISTINCT FLOOR(lsl.timecreated / 86400)) uniqueviewdays
            FROM {user} u
                LEFT OUTER JOIN {logstore_standard_log} lsl
                    ON lsl.userid = u.id
            WHERE lsl.eventname = :lsleventname
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

function block_forum_report_get_mulutimedia_num($text)
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
        $multimedia = block_forum_report_get_mulutimedia_num($post->message);
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

function block_forum_report_executeschedule(stdClass $schedule) {
    global $DB;

    $modcontextidlookup = block_forum_report_getforummodcontextidlookup($schedule->course);

    $engagementcalculators = [];
    $discussions = $DB->get_records('forum_discussions', $schedule->forum ? ['forum' => $schedule->forum] : ['course' => $schedule->course], '', 'id');
    foreach ($discussions as $discussion) {
        $engagementcalculators[] = engagement::getinstancefrommethod($schedule->engagementmethod, $discussion->id, $schedule->starttime, $schedule->endtime, $schedule->engagementinternational);
    }

    $students = block_forum_report_getbasicreports(
        block_forum_report_getschedulecontext($schedule),
        $schedule->course,
        $schedule->forum,
        $schedule->groupid,
        $schedule->country,
        $schedule->starttime,
        $schedule->endtime
    );

    $results = [];
    foreach ($students as $student) {
        $result = new stdClass();
        $result->schedule = $schedule->id;
        $result->username = $student->username;
        $result->firstname = $student->firstname;
        $result->lastname = $student->lastname;
        $result->groups = $student->groupnames;
        $result->country = $student->country;
        $result->instituion = $student->instituion;
        $result->posts = $student->posts;
        $result->replies = $student->replies;
        $result->uniquedaysactive = $student->unique_activedays;
        $result->views = $student->viewscount;
        $result->uniquedaysviewed = $student->uniqueviewdays;
        $result->firstpost = $student->firstpost;
        $result->lastpost = $student->lastpost;

        $multimedia = block_forum_report_countwordmultimedia(
            $modcontextidlookup,
            $student->id,
            $schedule->course,
            $schedule->forum,
            $schedule->starttime,
            $schedule->endtime
        );
        $result->wordcount = $multimedia->wordcount;
        $result->multimedia = $multimedia->multimedia;
        $result->images = $multimedia->multimedia_image;
        $result->videos = $multimedia->multimedia_video;
        $result->audios = $multimedia->multimedia_audio;
        $result->links = $multimedia->multimedia_link;

        $engagementresult = new engagementresult();
        foreach ($engagementcalculators as $engagementcalculator) {
            $engagementresult->add(($engagementcalculator->calculate($student->id)));
        }
        $result->engagement1 = $engagementresult->getl1();
        $result->engagement2 = $engagementresult->getl2();
        $result->engagement3 = $engagementresult->getl3();
        $result->engagement4 = $engagementresult->getl4up();
        $result->averageengagement = $engagementresult->getaverage();
        $result->maximumengagement = $engagementresult->getmax();

        if (block_forum_report_reactforuminstalled()) {
            $result->reactionsgiven = block_forum_report_getreactionsgiven($student->id, $schedule->course, $schedule->forum, $schedule->starttime, $schedule->endtime);
            $result->reactionsreceived = block_forum_report_getreactionsreceived($student->id, $schedule->course, $schedule->forum, $schedule->starttime, $schedule->endtime);
        }

        $results[] = $result;
    }
    $DB->insert_records('forum_report_results', $results);
}
