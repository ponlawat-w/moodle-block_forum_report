<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/../../lib/tablelib.php');
require_once(__DIR__ . '/reportlib.php');
require_once(__DIR__ . '/classes/engagement.php');

$startnow = optional_param('startnow', 0, PARAM_INT);
$forumid = optional_param('forum', 0, PARAM_INT);
$courseid = required_param('course', PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);
$countryid = optional_param('country', '', PARAM_RAW);
$start = optional_param('start', '', PARAM_RAW);
$end = optional_param('end', '', PARAM_RAW);
$perpage = optional_param('perpage', 0, PARAM_RAW);
$engagementmethod = optional_param('engagementmethod', null, PARAM_INT);
$engagementinternational = optional_param('engagementinternational', false, PARAM_BOOL);
$page = optional_param('page', 0, PARAM_RAW);
$tsort = optional_param('tsort', 0, PARAM_RAW);
if (strpos($tsort, 'name') !== FALSE) {
    $orderbyname = $tsort;
} else {
    $orderbyname = '';
}
$params['course'] = $courseid;
$course = $DB->get_record('course', array('id' => $courseid));

require_course_login($course);
$coursecontext = \core\context\course::instance($course->id);

require_capability('block/forum_report:view', $coursecontext, NULL, true, 'noviewdiscussionspermission', 'forum');

if ($forumid) {
    $params['forum'] = $forumid;
    $forum = $DB->get_record('forum', array('id' => $forumid));
    $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
    $modcontext = \core\context\module::instance($cm->id);
    $PAGE->set_title("$course->shortname: $forum->name");
    $PAGE->navbar->add($forum->name);
}

$countries = get_string_manager()->get_list_of_countries();

$mform = new report_form($courseid);
$fromform = $mform->get_data();
$paramstr = '?course=' . $course->id . '&forum=' . $forumid;

if ($groupid) {
    $params['group'] = $groupid;
    $groupfilter = $groupid;;
    $paramstr .= '&group=' . $groupfilter;
    $groupname = groups_get_all_groups($course->id)[$groupid]->name;
    /*
}elseif(isset($fromform->group)){
    $groupfilter = $fromform->group;
    $paramstr .= '&group='.$groupfilter;
    $params['group'] = $groupfilter;
    echo $groupfilter
    $groupname = groups_get_all_groups($course->id)[$groupfilter]->name;
*/
} else {
    $groupfilter = 0;
    $groupname = "";
}
if ($countryid) {
    $params['country'] = $countryid;
    $countryfilter = $countryid;
    $paramstr .= '&country=' . $countryfilter;
} elseif (isset($fromform->country)) {
    $countryfilter = $fromform->country;
    $paramstr .= '&country=' . $countryfilter;
    $params['country'] = $countryfilter;
} else {
    $countryfilter = 0;
}
if (isset($fromform->starttime)) {
    $starttime = $fromform->starttime;
    $params['start'] = $starttime;
    $paramstr .= '&start=' . $starttime;
} elseif ($start) {
    $starttime = $start;
    $paramstr .= '&start=' . $starttime;
    $params['start'] = $starttime;
} else {
    $starttime = 0;
}
//BL Customization
if (isset($page)) {
    $paramstr .= '&page=' . $page;
    $params['page'] = $page;
}
if (isset($perpage)) {
    $paramstr .= '&perpage=' . $perpage;
    $params['perpage'] = $perpage;
}
//BL Customization
if (isset($fromform->endtime)) {
    $endtime = $fromform->endtime;
    $params['end'] = $endtime;
    $paramstr .= '&end=' . $endtime;
} elseif ($end) {
    $endtime = $end;
    $paramstr .= '&end=' . $endtime;
    $params['end'] = $endtime;
} else {
    $endtime = 0;
}
if (isset($fromform->engagementmethod)) {
    $engagementmethod = $fromform->engagementmethod;
    $params['engagementmethod'] = $engagementmethod;
    $paramstr .= '&engagementmethod=' . $engagementmethod;
} else if ($engagementmethod) {
    $params['engagementmethod'] = $engagementmethod;
    $paramstr .= '&engagementmethod=' . $engagementmethod;
} else {
    $engagementmethod = -1;
}

if (isset($fromform->engagementinternational)) {
    $engagementinternational = $fromform->engagementinternational;
    $params['engagementinternational'] = $engagementinternational;
    $paramstr .= '&engagementinternational=' . ($engagementinternational ? 1 : 0);
} else if ($engagementinternational) {
    $params['engagementinternational'] = $engagementinternational;
    $paramstr .= '&engagementinternational=' . ($engagementinternational ? 1 : 0);
}

$PAGE->set_pagelayout('incourse');
/// Output the page
$PAGE->requires->jquery();
$PAGE->requires->js('/blocks/forum_report/scripts.js');
$PAGE->requires->css('/blocks/forum_report/styles.css');
$PAGE->set_url($CFG->wwwroot . '/blocks/forum_report/report.php', $params);
$PAGE->navbar->add('forum_report');
$PAGE->set_heading($course->fullname);
$PAGE->requires->js_call_amd('block_forum_report/script', 'init');
echo $OUTPUT->header();
$mform->display();
echo html_writer::tag('input','',array('type'=>'hidden','id'=>'my_courseid','value'=>$courseid));

$reactforuminstalled = block_forum_report_reactforuminstalled();

$strname = get_string('fullname');
$strfirstname = get_string('firstname');
$strlastname = get_string('lastname');
$strcounrty = get_string('country');
$strposts = get_string('posts');
$strviews = get_string('views', 'block_forum_report');
$strreplies = get_string('replies', 'block_forum_report');
$strwordcount = get_string('wordcount', 'block_forum_report');
$strimage = get_string('multimedia_image', 'block_forum_report');
$strvideo = get_string('multimedia_video', 'block_forum_report');
$straudio = get_string('multimedia_audio', 'block_forum_report');
$strlink = get_string('multimedia_link', 'block_forum_report');
$strel1 = get_string('el1', 'block_forum_report');
$strel2 = get_string('el2', 'block_forum_report');
$strel3 = get_string('el3', 'block_forum_report');
$strel4up = get_string('el4up', 'block_forum_report');
$strelavg = get_string('elavg', 'block_forum_report');
$strelmax = get_string('elmax', 'block_forum_report');
$strfp = get_string('firstpost', 'block_forum_report');
$strlp = get_string('lastpost', 'block_forum_report');
$strsr = get_string('sendreminder', 'block_forum_report');
$strcl = get_string('completereport');
$strinstituion = get_string('institution');
$strgroup = get_string('group');
$strmultimedia = get_string('multimedia', 'block_forum_report');
$struniqueview = get_string('uniqueview', 'block_forum_report');
$struniqueactive = get_string('uniqueactive', 'block_forum_report');
$strreactionsgiven = get_string('reactionsgiven', 'block_forum_report');
$strreactionsreceived = get_string('reactionsreceived', 'block_forum_report');

if (!$startnow) {
    block_forum_report_checkpermission($courseid, $groupid);

    echo '<br>';

    $modcontextidlookup = block_forum_report_getforummodcontextidlookup($course->id);

    $table = new flexible_table('forum_report_table');
    //$table->head = array($strname,$strcounrty,$strposts,$strreplies,$strwordcount,$strviews,$strfp,$strlp,$strsr,$strcl);
    //$table->define_align = array ("center","center","center","center","center","center","center","center","center","center");
    $table->define_baseurl($PAGE->url);

    $columns = [
        'firstname', 'lastname', 'group', 'country', 'institution',
        'posts', 'replies', 'unique_activedays', 'views', 'uniqueviewdays',
        'wordcount', 'multimedia', 'multimedia_image', 'multimedia_video', 'multimedia_audio', 'multimedia_link',
        'el1', 'el2', 'el3', 'el4up', 'elavg', 'elmax',
        'firstpost', 'lastpost'
    ];
    if ($reactforuminstalled) {
        $columns[] = 'reactionsgiven';
        $columns[] = 'reactionsreceived';
    }
    $columns[] = 'action';
    $table->define_columns($columns);

    $headers = [
        $strfirstname, $strlastname, $strgroup, $strcounrty, $strinstituion,
        $strposts, $strreplies, $struniqueactive, $strviews, $struniqueview,
        $strwordcount, $strmultimedia, $strimage, $strvideo, $straudio, $strlink,
        $strel1,$strel2,$strel3,$strel4up,$strelavg,$strelmax,
        $strfp, $strlp
    ];
    if ($reactforuminstalled) {
        $headers[] = $strreactionsgiven;
        $headers[] = $strreactionsreceived;
    }
    $headers[] = '';
    $table->define_headers($headers);

    $table->sortable(true);
    $table->collapsible(true);
    $table->set_attribute('class', 'admintable generaltable');
    $table->setup();
    $sortby = $table->get_sort_columns();
    if ($sortby) {
        $orderby = array_keys($sortby)[0];
        $ascdesc = ($sortby[$orderby] == 4) ? 'ASC' : 'DESC';
        if (strpos($orderby, 'name') !== FALSE) {
            $orderbyname = $orderby . ' ' . $ascdesc;
        } else {
            $orderbyname = '';
        }
    } else {
        $orderbyname = '';
    }

    //get_enrolled_users(context $context, $withcapability = '', $groupid = 0, $userfields = 'u.*', $orderby = '', $limitfrom = 0, $limitnum = 0)に変えること
    //$students = get_enrolled_users($coursecontext);
    //var_dump($students);

    $engagementcalculators = [];
    /** @var \moodle_database $DB */
    $discussions = $DB->get_records('forum_discussions', $forumid ? ['forum' => $forumid] : ['course' => $courseid], '', 'id');
    foreach ($discussions as $discussion) {
        $engagementcalculators[] = \block_forum_report\engagement::getinstancefrommethod($engagementmethod, $discussion->id, $starttime, $endtime, $engagementinternational);
    }

    $data = array();

    $students = block_forum_report_getbasicreports(
        $forumid ? $modcontext : $coursecontext,
        $courseid,
        $forumid,
        $groupid,
        $countryfilter,
        $starttime,
        $endtime
    );

    foreach ($students as $student) {
        $studentdata = new stdClass();

        $studentdata->id = $student->id;

        //Name
        $studentdata->firstname = $student->firstname;
        $studentdata->lastname = $student->lastname;

        $studentdata->group = $student->groupnames;

        //Countryfullname($student);
        $studentdata->country = @$countries[$student->country];

        //Instituion
        $studentdata->institution = $student->institution;

        //Posts
        $studentdata->posts = $student->posts;
        $studentdata->replies = $student->replies;

        //BL Customization
        //Unique active days
        $studentdata->unique_activedays = $student->unique_activedays;

        //BL Customization
        //View
        $studentdata->views = $student->viewscount;
        $studentdata->uniqueviewdays = $student->uniqueviewdays;

        //BL Customization
        //Word count and multimedia
        $multimedia = block_forum_report_countwordmultimedia($modcontextidlookup, $student->id, $courseid, $forumid, $starttime, $endtime);

        $studentdata->wordcount = $multimedia->wordcount;
        $studentdata->multimedia = $multimedia->multimedia;
        $studentdata->multimedia_image = $multimedia->multimedia_image;
        $studentdata->multimedia_video = $multimedia->multimedia_video;
        $studentdata->multimedia_audio = $multimedia->multimedia_audio;
        $studentdata->multimedia_link = $multimedia->multimedia_link;

        //BL Customization
        // Engagement levels
        $engagementresult = new \block_forum_report\engagementresult();
        foreach ($engagementcalculators as $engagementcalculator) {
            $engagementresult->add($engagementcalculator->calculate($student->id));
        }
        $studentdata->el1 = $engagementresult->getl1();
        $studentdata->el2 = $engagementresult->getl2();
        $studentdata->el3 = $engagementresult->getl3();
        $studentdata->el4up = $engagementresult->getl4up();
        $studentdata->elavg = $engagementresult->getaverage();
        $studentdata->elmax = $engagementresult->getmax();

        $studentdata->firstpost = $student->firstpost ? userdate($student->firstpost) : '-';
        $studentdata->lastpost = $student->lastpost ? userdate($student->lastpost) : '-';

        if ($reactforuminstalled) {
            $studentdata->reactionsgiven = block_forum_report_getreactionsgiven($student->id, $courseid, $forumid, $starttime, $endtime);
            $studentdata->reactionsreceived = block_forum_report_getreactionsreceived($student->id, $courseid, $forumid, $starttime, $endtime);
        }

        $data[] = $studentdata;
    }
    if ($sortby && !$orderbyname) {
        usort($data, forum_report_sort($sortby));
    }
    //BL Customization
    //Number of records per page
    if ($perpage) {
        $table->pagesize($perpage, count($data));
        $data = array_slice($data, $page * $perpage, $perpage);
    }
    //BL Customization
    foreach ($data as $row) {
        //Notification
        //$output = $OUTPUT->pix_icon('t/subscribed', get_string('sendreminder', 'block_forum_report'), 'mod_forum');
        $output = '<span class="forumreporticon-envelop" title="Send reminder"></span>';
        $sendreminder = '<a href="#" onclick="sendreminder(' . $row->id . ')">' . $output . '</a>';
        //message_sendを別phpで発火させる発火させる
        $compurl = $CFG->wwwroot . '/report/outline/user.php?id=' . $row->id . '&course=' . $course->id . '&mode=complete';
        $complink = '<a href="' . $compurl . '"><span class="forumreporticon-profile" title="Complete reports"></span></a>';
        //$table->data[] = array($row->name,$row->country,$row->posts,$row->replies,$row->wordcount,$row->views,$row->firstpost,$row->lastpost,$sendreminder,$complink);
        $trdata = [
            $row->firstname, $row->lastname, $row->group, $row->country, $row->institution,
            $row->posts, $row->replies, $row->unique_activedays, $row->views, $row->uniqueviewdays,
            $row->wordcount, $row->multimedia, $row->multimedia_image, $row->multimedia_video, $row->multimedia_audio, $row->multimedia_link,
            $row->el1, $row->el2, $row->el3, $row->el4up, $row->elavg, $row->elmax,
            $row->firstpost, $row->lastpost];
        if ($reactforuminstalled) {
            $trdata[] = $row->reactionsgiven;
            $trdata[] = $row->reactionsreceived;
        }
        $trdata[] = $sendreminder . $complink;
        $table->add_data($trdata);
    }
    echo '<input type="hidden" name="course" id="courseid" value="' . $courseid . '">';
    if ($forumid) {
        echo '<input type="hidden" name="forum" id="forumid" value="' . $forumid . '">';
    }
    $table->finish_output();
    //echo html_writer::table($table);
}
echo $OUTPUT->footer();
