<?php

const BLOCK_FORUM_REPORT_STATUS_SCHEDULED = 0;
const BLOCK_FORUM_REPORT_STATUS_EXECUTING = 1;
const BLOCK_FORUM_REPORT_STATUS_ERROR = 2;
const BLOCK_FORUM_REPORT_STATUS_FINISH = 3;

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
    $current = date('G');
    $hrs = explode(',', get_config('block_forum_report', 'executionschedule'));
    if (!count($hrs)) return null;

    foreach ($hrs as $hr) {
        if (!is_numeric($hr)) continue;
        if ($current >= $hr) continue;
        return mktime($hr, 0, 0);
    }
    if (!is_numeric($hrs[0])) return null;

    return mktime($hrs[0] + 24, 0, 0);
}

function block_forum_report_getreportscontext(int $userid) {
    global $DB;
    $reports = [];

    $records = $DB->get_records('forum_report_schedules', ['userid' => $userid], 'createdtime');
    foreach ($records as $record) {
        $scheduledtime = $record->status == BLOCK_FORUM_REPORT_STATUS_SCHEDULED ? block_forum_report_getnextscheduledtime() : $record->processedtime;

        $report = [];
        $report['requestedtime'] = userdate($record->createdtime, get_string('strftimedaydatetime', 'langconfig'));
        $report['scheduledtime'] = $scheduledtime ? userdate($scheduledtime, get_string('strftimedaydatetime', 'langconfig')) : '-';
        $report['status'] = 0;
        $report['statusClass'] = '';
        $report['viewurl'] = new moodle_url('/blocks/forum_report/view.php', ['id' => $record->id]);

        if ($record->status == BLOCK_FORUM_REPORT_STATUS_SCHEDULED) {
            $report['status'] = get_string('status_scheduled', 'block_forum_report');
            $report['statusClass'] = '';
        } else if ($record->status == BLOCK_FORUM_REPORT_STATUS_EXECUTING) {
            $report['status'] = get_string('status_executing', 'block_forum_report');
            $report['statusClass'] = 'text-primary';
        } else if ($record->status == BLOCK_FORUM_REPORT_STATUS_ERROR) {
            $report['status'] = get_string('status_error', 'block_forum_report');
            $report['statusClass'] = 'text-danger';
        } else if ($record->status == BLOCK_FORUM_REPORT_STATUS_FINISH) {
            $report['status'] = get_string('status_finish', 'block_forum_report');
            $report['statusClass'] = 'text-success';
        }

        $reports[] = $report;
    }

    return ['reports' => $reports];
}
