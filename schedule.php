<?php

use block_forum_report\forms\schedule_form;

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/forms/schedule_form.php');

/**
 * @var \moodle_database $DB 
 * @var \moodle_page $PAGE
 * @var \core_renderer $OUTPUT
 */

$courseid = required_param('course', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course->id);

$coursecontext = \core\context\course::instance($courseid);
require_capability('block/forum_report:view', $coursecontext, NULL, true, 'noviewdiscussionpermissionm', 'forum');

$hasreportinqueue = $DB->count_records('forum_report_schedules', ['userid' => $USER->id, 'status' => BLOCK_FORUM_REPORT_STATUS_SCHEDULED]) > 0;
$form = new schedule_form($course->id, !$hasreportinqueue);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
    exit;
}
if ($form->is_submitted()) {
    $data = $form->get_data();
    block_forum_report_removeexistingschedule();
    block_forum_report_addschedule($data);
    redirect(new moodle_url('/blocks/forum_report/schedule.php', ['course' => $course->id]));
    exit;
}

$PAGE->set_pagelayout('incourse');
$PAGE->set_url(new moodle_url('/blocks/forum_report/schedule.php', ['course' => $course->id]));
$PAGE->navbar->add('forum_report');
$PAGE->set_heading($course->fullname);
$PAGE->set_title(get_string('requestnewreport', 'block_forum_report'));

echo $OUTPUT->header();
echo $form->render();
echo $OUTPUT->render_from_template('block_forum_report/myreports', block_forum_report_getreportscontext($USER->id));
echo $OUTPUT->footer();
