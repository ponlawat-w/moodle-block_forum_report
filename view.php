<?php

use block_forum_report\engagement;
use block_forum_report\forms\deleteconfirm_form;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/forms/deleteconfirm_form.php');

/**
 * @var \moodle_database $DB
 * @var \moodle_page $PAGE
 * @var \core_renderer $OUTPUT
 */

$id = required_param('id', PARAM_INT);
$action = optional_param('action', 'view', PARAM_TEXT);

$schedule = $DB->get_record('forum_report_schedules', ['id' => $id], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $schedule->course], '*', MUST_EXIST);
require_login($course->id);

$coursecontext = \core\context\course::instance($course->id);
$blockcontext = block_forum_report_getblockcontext($coursecontext);
require_capability('block/forum_report:view', $blockcontext, NULL, true, 'noviewdiscussionpermissionm', 'forum');

if ($USER->id != $schedule->userid) throw new \moodle_exception('You don\'t have permission to view this report');

$deleteform = $action === 'delete' ? new deleteconfirm_form($schedule->id) : null;

if ($action === 'download') {
    if ($schedule->status != BLOCK_FORUM_REPORT_STATUS_FINISH) {
        throw new \moodle_exception('This report is not ready for download');
    }
    require_once(__DIR__ . '/../../lib/csvlib.class.php');
    $csv = new \csv_export_writer();
    $csv->set_filename('forum_report');
    $csv->add_data(block_forum_report_getresultsheader());
    $results = $DB->get_records('forum_report_results', ['schedule' => $schedule->id]);
    foreach ($results as $result) {
        $csv->add_data(block_forum_report_getresultsrow($result));
    }
    $csv->download_file();
    exit;
} else if ($action === 'delete') {
    if ($deleteform->is_submitted()) {
        if ($deleteform->is_cancelled()) {
            redirect(new \moodle_url('/blocks/forum_report/view.php', ['id' => $schedule->id]));
            exit;
        }
        block_forum_report_removeschedule($schedule->id);
        redirect(new \moodle_url('/blocks/forum_report/schedule.php', ['course' => $coursecontext->instanceid]));
        exit;
    }
}

$PAGE->set_pagelayout('incourse');
$PAGE->set_url(new moodle_url('/blocks/forum_report/view.php', ['id' => $schedule->id]));
$PAGE->navbar->add(
    get_string('pluginname', 'block_forum_report'),
    new \moodle_url('/blocks/forum_report/schedule.php', ['course' => $course->id])
);
$PAGE->navbar->add(get_string('reportschedule', 'block_forum_report'));
$PAGE->set_heading(get_string('reportschedule', 'block_forum_report'));
$PAGE->set_title(get_string('reportschedule', 'block_forum_report'));

echo $OUTPUT->header();

if ($action === 'view') {
    $scheduledtime = block_forum_report_getscheduledtime($schedule);
    $status = block_forum_report_getstatus($schedule->status);
    $forum = $schedule->forum ? get_coursemodule_from_instance('forum', $schedule->forum, 0, false, MUST_EXIST) : null;
    echo $OUTPUT->render_from_template('block_forum_report/scheduleinfo', [
        'createdby' => fullname($DB->get_record('user', ['id' => $schedule->userid], '*', MUST_EXIST)),
        'requestedtime' => userdate($schedule->createdtime, get_string('strftimedaydatetime', 'langconfig')),
        'scheduledtime' => $scheduledtime ? userdate($scheduledtime, get_string('strftimedaydatetime', 'langconfig')) : '-',
        'status' => $status[0],
        'statusclass' => $status[1],
        'country' => $schedule->country ? get_string_manager()->get_list_of_countries()[$schedule->country] : get_string('all'),
        'group' => $schedule->groupid ?
            $DB->get_record('groups', ['id' => $schedule->groupid], '*', MUST_EXIST)->name
            : get_string('all'),
        'forum' => $schedule->forum ? $forum->name : get_string('all'),
        'starttime' => $schedule->starttime ? userdate($schedule->starttime, get_string('strftimedaydatetime', 'langconfig')) : '-',
        'endtime' => $schedule->endtime ? userdate($schedule->endtime, get_string('strftimedaydatetime', 'langconfig')) : '-',
        'engagementmethod' => engagement::getname($schedule->engagementmethod),
        'engagementinternational' => $schedule->engagementinternational ? get_string('yes') : get_string('no'),
        'downloadurl' => block_forum_report_getdownloadurl($schedule),
        'deleteurl' => block_forum_report_getdeleteurl($schedule)
    ]);
    
    if ($schedule->status == BLOCK_FORUM_REPORT_STATUS_FINISH) {
        $results = $DB->get_records('forum_report_results', ['schedule' => $schedule->id]);
        $rows = [];
        foreach ($results as $result) $rows[] = [
            'records' => block_forum_report_getresultsrow($result),
            'reporturl' => new moodle_url('/report/outline/user.php', [
                'id' => $result->userid,
                'course' => $schedule->course,
                'mode' => 'complete'
            ])
        ];
    
        echo $OUTPUT->render_from_template('block_forum_report/results', [
            'headers' => block_forum_report_getresultsheader(),
            'rows' => $rows,
            'empty' => count($rows) === 0
        ]);
    }
} else if ($action === 'delete') {
    $deleteform->display();
    echo html_writer::start_tag('hr');
}

echo $OUTPUT->footer();
