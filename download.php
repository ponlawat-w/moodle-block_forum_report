<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../lib/csvlib.class.php');
require_once(__DIR__ . '/reportlib.php');
require_once(__DIR__ . '/classes/engagement.php');

$forumid = optional_param('forum', 0, PARAM_INT);
$courseid = required_param('course', PARAM_INT);
$countryfilter = optional_param('country', 0, PARAM_RAW);
$groupfilter = optional_param('group', 0, PARAM_INT);
$starttime = optional_param('starttime', '', PARAM_RAW);
$endtime = optional_param('endtime', '', PARAM_RAW);
$engagementmethod = required_param('engagementmethod', PARAM_INT);
$engagementinternational = required_param('engagementinternational', PARAM_INT) ? true : false;
$course = $DB->get_record('course', array('id' => $courseid));
require_course_login($course);
$coursecontext = \core\context\course::instance($course->id);

if ($forumid) {
    $forum = $DB->get_record('forum', array('id' => $forumid));
    $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
    $modcontext = \core\context\module::instance($cm->id);
}
require_capability('block/forum_report:view', $coursecontext, NULL, true, 'noviewdiscussionspermission', 'forum');
block_forum_report_checkpermission($courseid, $groupfilter);


$students = block_forum_report_getbasicreports(
    $forumid ? $modcontext : $coursecontext,
    $courseid,
    $forumid,
    $groupfilter,
    $countryfilter,
    $starttime,
    $endtime
);

$countries = get_string_manager()->get_list_of_countries();

$modcontextidlookup = block_forum_report_getdiscussionmodcontextidlookup($course->id);

$engagementcalculators = [];
/** @var \moodle_database $DB */
$discussions = $DB->get_records('forum_discussions', $forumid ? ['forum' => $forumid] : ['course' => $courseid], '', 'id');
foreach ($discussions as $discussion) {
    $engagementcalculators[] = \block_forum_report\engagement::getinstancefrommethod($engagementmethod, $discussion->id, $starttime, $endtime, $engagementinternational);
}

$data = array();

$reactforuminstalled = block_forum_report_reactforuminstalled();

foreach ($students as $student) {
    $studentdata = array();

    if ($countryfilter && $countryfilter != $student->country) {
        continue;
    }

    //Username
    $studentdata[] = $student->username; // temporarily removed
    //Name
    $studentdata[] = $student->firstname;
    $studentdata[] = $student->lastname;
    //Group
    $studentdata[] = $student->groupnames;
    //Countryfullname($student);
    $studentdata[] = @$countries[$student->country];

    //Instituion
    $studentdata[] = $student->institution;

    //Posts
    $studentdata[] = $student->posts;

    //Replies
    $studentdata[] = $student->replies;

    //BL Customization
    //Unique_activedays
    $studentdata[] = $student->unique_activedays;

    //BL Customization
    //View
    $studentdata[] = $student->viewscount;
    $studentdata[] = $student->uniqueviewdays;

    //BL Customization
    // Word count and multimedia
    $multimedia = block_forum_report_countwordmultimedia($modcontextidlookup, $student->id, $courseid, $forumid, $starttime, $endtime);
    $studentdata[] = $multimedia->wordcount;
    $studentdata[] = $multimedia->multimedia;
    $studentdata[] = $multimedia->multimedia_image;
    $studentdata[] = $multimedia->multimedia_video;
    $studentdata[] = $multimedia->multimedia_audio;
    $studentdata[] = $multimedia->multimedia_link;

    // Engagement levels
    $engagementresult = new \block_forum_report\engagementresult();
    foreach ($engagementcalculators as $engagementcalculator) {
        $engagementresult->add($engagementcalculator->calculate($student->id));
    }
    $studentdata[] = $engagementresult->getl1();
    $studentdata[] = $engagementresult->getl2();
    $studentdata[] = $engagementresult->getl3();
    $studentdata[] = $engagementresult->getl4up();
    $studentdata[] = $engagementresult->getaverage();
    $studentdata[] = $engagementresult->getmax();

    //First post & Last post
    $studentdata[] = $student->firstpost ? userdate($student->firstpost) : '-';
    $studentdata[] = $student->lastpost ? userdate($student->lastpost) : '-';

    if ($reactforuminstalled) {
        $studentdata[] = block_forum_report_getreactionsgiven($student->id, $courseid, $forumid, $starttime, $endtime);
        $studentdata[] = block_forum_report_getreactionsreceived($student->id, $courseid, $forumid, $starttime, $endtime);
    }

    $data[] = $studentdata;
}

$csvexport = new \csv_export_writer();
$filename = 'forum-report';
$csvexport->set_filename($filename);
$csvexport->add_data(array(
    'Username', 'First Name', 'Last Name', 'Group', 'Country', 'Instituion',
    'Posts', 'Replies', 'Unique days active', 'Views', 'Unique days viewed',
    'Word count', 'Multimedia', 'Images', 'Videos', 'Audios', 'Links',
    'Engagement#1', 'Engagement#2', 'Engagement#3', 'Engagement#4', 'Average Engagement', 'Maximum Engagement',
    'First post', 'Last post', 'Reactions Given', 'Reactions Received'
));
foreach ($data as $line) {
    $csvexport->add_data($line);
}
$csvexport->download_file();
