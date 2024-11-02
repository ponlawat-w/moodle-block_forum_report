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

function block_forum_report_getblockcontext(\core\context\course $coursecontext) {
    global $DB;
    $block = $DB->get_record('block_instances', ['blockname' => 'forum_report', 'parentcontextid' => $coursecontext->id], '*', MUST_EXIST);
    return \core\context\block::instance($block->id);
}

function block_forum_report_addschedule(stdClass $formdata, \core\context\block $blockcontext, int $userid = 0) {
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

    $schedule->id = $DB->insert_record('forum_report_schedules', $schedule);

    if ($formdata->instant && has_capability('block/forum_report:getinstantreport', $blockcontext)) {
        block_forum_report_executeschedule($schedule);
    }

    return $schedule->id;
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
    return new moodle_url('/blocks/forum_report/view.php', ['id' => $schedule->id, 'action' => 'delete']);
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
    $userid,
    $context,
    $courseid,
    $forumid = 0,
    $groupid = 0,
    $country = null,
    $starttime = 0,
    $endtime = 0
) {
    global $DB;

    $coursecontext = \core\context\course::instance($courseid);
    $blockcontext = block_forum_report_getblockcontext($coursecontext);

    $capacityjoin = get_with_capability_join($context, 'mod/forum:viewdiscussion', 'u.id');
    $params = $capacityjoin->params;

    if (has_capability('block/forum_report:viewothergroups', $blockcontext, $userid)) {
        $groupjoin = $groupid ? 'JOIN' : 'LEFT OUTER JOIN';
        $groupcondition = $groupid ? 'AND ug.id = :group' : '';
        if ($groupid) {
            $params['group'] = $groupid;
        }
    } else {
        $groupjoin = 'JOIN';
        $mygroups = groups_get_user_groups($courseid, $userid);
        $mygroupids = [];
        foreach ($mygroups[0] as $mygroupid) $mygroupids[] = $mygroupid;

        if (!count($mygroupids)) return [];
        if ($groupid && !in_array($groupid, $mygroupids)) return [];

        $querygroupids = $groupid ? [$groupid] : $mygroupids;
        [$groupsql, $groupparams] = $DB->get_in_or_equal($querygroupids, SQL_PARAMS_NAMED, 'groupid_');

        $groupcondition = 'AND ug.id ' . $groupsql;
        $params = array_merge($params, $groupparams);
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

    $count = new stdClass();
    $count->num = 0;
    $count->img = 0;
    $count->video = 0;
    $count->audio = 0;
    $count->link = 0;

    if (!is_string($text) or empty($text)) {
        // non string data can not be filtered anyway
        return $count;
    }

    if (stripos($text, '</a>') === false && stripos($text, '</video>') === false && stripos($text, '</audio>') === false && (stripos($text, '<img') === false)) {
        // Performance shortcut - if there are no </a>, </video> or </audio> tags, nothing can match.
        return $count;
    }

    // Looking for tags.
    $matches = preg_split('/(<[^>]*>)/i', $text, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
    if (!$matches) {
        return $count;
    } else {
        // Regex to find media extensions in an <a> tag.
        $embedmarkers = core_media_manager::instance()->get_embeddable_markers();

        $tagname = '';
        foreach ($matches as $idx => $tag) {
            if (preg_match('/<(a|img|video|audio)\s[^>]*/', $tag, $tagmatches)) {
                $tagname = strtolower($tagmatches[1]);
                if ($tagname === "a") {
                    preg_match("<a\\s+href=\".*({$embedmarkers}).*\".*>", $tag, $embedmarkermatch);
                    $embed = $embedmarkermatch[1] ? $embedmarkermatch[1] : null;
                    if (
                        $embed == '.fmp4' || $embed == '.mov' || $embed == '.mp4' || $embed == '.m4v' || $embed == '.ogv' || $embed == '.webm'
                        || $embed == 'youtube.com' || $embed == 'youtube-nocookie.com' || $embed == 'youtu.be' || $embed == 'y2u.be'
                        || $embed == 'youtube.com' || $embed == 'youtube-nocookie.com' || $embed == 'youtu.be' || $embed == 'y2u.be'
                    ) {
                        $count->video++;
                        $count->num++;
                    } else if (
                        $embed == '.m3u8' || $embed == '.mpd' || $embed == '.aac' || $embed == '.flac' || $embed == '.mp3'
                        || $embed == '.m4a' || $embed == '.oga' || $embed == '.ogg' || $embed == '.wav'
                    ) {
                        $count->audio++;
                        $count->num++;
                    } else {
                        $count->link++;
                        $count->num++;
                    }
                } else if ($tagname == "img") {
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
            WHERE fp.userid = :userid
                AND ((:forumid1 = 0 AND fd.course = :courseid) OR fd.forum = :forumid2)
                {$timecondition}
    SQL;
    $params = [
        'userid' => $userid,
        'courseid' => $courseid,
        'forumid1' => $forumid ? $forumid : 0,
        'forumid2' => $forumid ? $forumid : 0
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
    return isset(\core\plugin_manager::instance()->get_installed_plugins('local')['reactforum']);
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
        'forumid1' => $forumid ? $forumid : 0,
        'forumid2' => $forumid ? $forumid : 0
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
        'forumid1' => $forumid ? $forumid : 0,
        'forumid2' => $forumid ? $forumid : 0
    ];
    if ($starttime) $params['starttime'] = $starttime;
    if ($endtime) $params['endtime'] = $endtime;
    return $DB->get_record_sql($sql, $params)->received;
}

function block_forum_report_executeschedule(stdClass $schedule) {
    global $DB;
    try {
        $schedule->status = BLOCK_FORUM_REPORT_STATUS_EXECUTING;
        $schedule->processedtime = time();
        $DB->update_record('forum_report_schedules', $schedule);

        $DB->execute(<<<SQL
            DELETE FROM {forum_report_results} WHERE schedule IN (
                SELECT id FROM {forum_report_schedules} WHERE userid = ? AND createdtime < ?
            )
        SQL, [$schedule->userid, $schedule->createdtime]);
        $DB->execute(<<<SQL
            DELETE FROM {forum_report_schedules} WHERE userid = ? AND createdtime < ?
        SQL, [$schedule->userid, $schedule->createdtime]);

        block_forum_report_calculatereport($schedule);

        $schedule->status = BLOCK_FORUM_REPORT_STATUS_FINISH;
        $schedule->processedtime = time();
        $DB->update_record('forum_report_schedules', $schedule);
        return true;
    } catch (\Throwable $ex) {
        $schedule->status = BLOCK_FORUM_REPORT_STATUS_ERROR;
        $schedule->message = $ex->getMessage() . PHP_EOL . $ex->getTraceAsString();
        $schedule->processedtime = time();
        $DB->update_record('forum_report_schedules', $schedule);
        return false;
    }
}

function block_forum_report_calculatereport(stdClass $schedule) {
    global $DB;

    $modcontextidlookup = block_forum_report_getforummodcontextidlookup($schedule->course);

    $engagementcalculators = [];
    $discussions = $DB->get_records('forum_discussions', $schedule->forum ? ['forum' => $schedule->forum] : ['course' => $schedule->course], '', 'id');
    foreach ($discussions as $discussion) {
        $engagementcalculators[] = engagement::getinstancefrommethod($schedule->engagementmethod, $discussion->id, $schedule->starttime, $schedule->endtime, $schedule->engagementinternational);
    }

    $students = block_forum_report_getbasicreports(
        $schedule->userid,
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
        $result->userid = $student->id;
        $result->username = $student->username;
        $result->firstname = $student->firstname;
        $result->lastname = $student->lastname;
        $result->groups = $student->groupnames;
        $result->country = $student->country;
        $result->institution = $student->institution;
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

function block_forum_report_getresultsheader() {
    return [
        'username' => get_string('username'),
        'firstname' => get_string('firstname'),
        'lastname' => get_string('lastname'),
        'groups' => get_string('group'),
        'country' => get_string('country'),
        'institution' => get_string('institution'),
        'posts' => get_string('posts'),
        'replies' => get_string('replies', 'block_forum_report'),
        'uniquedaysactive' => get_string('uniqueactive', 'block_forum_report'),
        'views' => get_string('views', 'block_forum_report'),
        'uniquedaysviewed' => get_string('uniqueview', 'block_forum_report'),
        'wordcount' => get_string('wordcount', 'block_forum_report'),
        'multimedia' => get_string('multimedia', 'block_forum_report'),
        'images' => get_string('multimedia_image', 'block_forum_report'),
        'videos' => get_string('multimedia_video', 'block_forum_report'),
        'audios' => get_string('multimedia_audio', 'block_forum_report'),
        'links' => get_string('multimedia_link', 'block_forum_report'),
        'engagement1' => get_string('el1', 'block_forum_report'),
        'engagement2' => get_string('el2', 'block_forum_report'),
        'engagement3' => get_string('el3', 'block_forum_report'),
        'engagement4' => get_string('el4up', 'block_forum_report'),
        'averageengagement' => get_string('elavg', 'block_forum_report'),
        'maximumengagement' => get_string('elmax', 'block_forum_report'),
        'firstpost' => get_string('firstpost', 'block_forum_report'),
        'lastpost' => get_string('lastpost', 'block_forum_report'),
        'reactionsgiven' => get_string('reactionsreceived', 'block_forum_report'),
        'reactionsreceived' => get_string('reactionsgiven', 'block_forum_report')
    ];
}

function block_forum_report_getresultsheadercontext($scheduleid, $sortname = 'userid', $sorttype = 'asc') {
  $sorttype = strtolower($sorttype);
  $differentsorttype = $sorttype == 'asc' ? 'desc' : 'asc';
  $items = [];
  foreach (block_forum_report_getresultsheader() as $fieldname => $title) {
    $items[] = [
      'name' => $title,
      'sorturl' => new \moodle_url(
        '/blocks/forum_report/view.php',
        ['id' => $scheduleid, 'sn' => $fieldname, 'sd' => $sortname === $fieldname ? $differentsorttype : 'asc'],
        'results'
      ),
      'icon' => $sortname == $fieldname ? ($sorttype == 'desc' ? 'fa-caret-down' : 'fa-caret-up') : null
    ];
  }
  return $items;
}

$_countries = [];
function block_forum_report_getresultsrow($record) {
    global $_countries;

    if (!count($_countries)) $_countries = get_string_manager()->get_list_of_countries();

    return [
        $record->username,
        $record->firstname,
        $record->lastname,
        $record->groups,
        $_countries[$record->country],
        $record->institution,
        $record->posts,
        $record->replies,
        $record->uniquedaysactive,
        $record->views,
        $record->uniquedaysviewed,
        $record->wordcount,
        $record->multimedia,
        $record->images,
        $record->videos,
        $record->audios,
        $record->links,
        $record->engagement1,
        $record->engagement2,
        $record->engagement3,
        $record->engagement4,
        $record->averageengagement,
        $record->maximumengagement,
        $record->firstpost ? userdate($record->firstpost, get_string('strftimedatetimeshortaccurate', 'langconfig')) : '',
        $record->lastpost ? userdate($record->lastpost, get_string('strftimedatetimeshortaccurate', 'langconfig')) : '',
        $record->reactionsgiven,
        $record->reactionsreceived
    ];
}

function block_forum_report_getsort($sortname, $sorttype) {
  if (
    !$sortname || (
      $sortname != 'username'
      && $sortname != 'firstname'
      && $sortname != 'lastname'
      && $sortname != 'groups'
      && $sortname != 'country'
      && $sortname != 'institution'
      && $sortname != 'posts'
      && $sortname != 'replies'
      && $sortname != 'uniquedaysactive'
      && $sortname != 'views'
      && $sortname != 'uniquedaysviewed'
      && $sortname != 'wordcount'
      && $sortname != 'multimedia'
      && $sortname != 'images'
      && $sortname != 'videos'
      && $sortname != 'audios'
      && $sortname != 'links'
      && $sortname != 'engagement1'
      && $sortname != 'engagement2'
      && $sortname != 'engagement3'
      && $sortname != 'engagement4'
      && $sortname != 'averageengagement'
      && $sortname != 'maximumengagement'
      && $sortname != 'firstpost'
      && $sortname != 'lastpost'
      && $sortname != 'reactionsgiven'
      && $sortname != 'reactionsreceived'
    )
  ) return 'userid ASC';
  if (strtolower($sorttype) != 'asc' && strtolower($sorttype) != 'desc') $sorttype = 'ASC';
  return "{$sortname} {$sorttype}";
}
