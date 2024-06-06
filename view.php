<?php

use block_forum_report\engagement;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

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
require_capability('block/forum_report:view', $coursecontext, NULL, true, 'noviewdiscussionpermissionm', 'forum');

if ($USER->id != $schedule->userid) throw new \moodle_exception('You don\'t have permission to view this report');

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

$scheduledtime = block_forum_report_getscheduledtime($schedule);
$status = block_forum_report_getstatus($schedule->status);
$forum = $schedule->forum ? get_coursemodule_from_instance('forum', $schedule->forum, 0, false, MUST_EXIST) : null;
echo $OUTPUT->render_from_template('block_forum_report/scheduleinfo', [
    'createdby' => fullname($DB->get_record('user', ['id' => $schedule->userid], '*', MUST_EXIST)),
    'requestedtime' => userdate($schedule->createdtime, get_string('strftimedaydatetime', 'langconfig')),
    'scheduledtime' => $scheduledtime ? userdate($scheduledtime, get_string('strftimedaydatetime', 'langconfig')) : '-',
    'status' => $status[0],
    'statusclass' => $status[1],
    'country' => $schedule->country ? $schedule->country : get_string('all'),
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

echo $OUTPUT->footer();
