<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once('reportlib.php');
require_once($CFG->libdir . '/csvlib.class.php');

$forumid = optional_param('forum', 0, PARAM_INT);
$courseid = required_param('course', PARAM_INT);
$countryfilter = optional_param('country', 0, PARAM_RAW);
$groupfilter = optional_param('group', 0, PARAM_INT);
$starttime = optional_param('starttime', '', PARAM_RAW);
$endtime = optional_param('endtime', '', PARAM_RAW);
$course = $DB->get_record('course', array('id' => $courseid));
require_course_login($course);
$coursecontext = context_course::instance($course->id);

if ($forumid) {
    $forum = $DB->get_record('forum', array('id' => $forumid));
    $cm = get_coursemodule_from_instance('forum', $forum->id, $course->id, false, MUST_EXIST);
    $modcontext = context_module::instance($cm->id);
}
require_capability('block/forum_report:view', $coursecontext, NULL, true, 'noviewdiscussionspermission', 'forum');

$students = get_users_by_capability($coursecontext, 'mod/forum:viewdiscussion');

$countries = get_string_manager()->get_list_of_countries();

//if (isset($fromform->starttime)) {
//    $starttime = $fromform->starttime;
//} elseif ($start) {
//    $starttime = $start;
//} else {
//    $starttime = 0;
//}
//if (isset($fromform->endtime)) {
//    $endtime = $fromform->endtime;
//} elseif ($end) {
//    $endtime = $end;
//} else {
//    $endtime = 0;
//}

if ($forumid) {
    $discussions = $DB->get_records('forum_discussions', array('forum' => $forum->id));
} else {
    $discussions = $DB->get_records('forum_discussions', array('course' => $course->id));
}

$discussionarray = '(';
foreach ($discussions as $discussion) {
    $discussionarray .= $discussion->id . ',';
}
$discussionarray .= '0)';

$data = array();

foreach ($students as $student) {
    $studentdata = array();

    if ($countryfilter && $countryfilter != $student->country) {
        continue;
    }

    //Check to join group
    $studentgroups = groups_get_all_groups($course->id, $student->id);
    $ingroups = array_keys($studentgroups);
    if ($groupfilter) {
        if (!in_array($groupfilter, $ingroups)) {
            continue;
        }
    }

    //Username
    $studentdata[] = $student->username;
    //Name
    $studentdata[] = fullname($student);
    //Group
    $tempgroups = array();
    foreach ($studentgroups as $studentgroup) {
        $tempgroups[] = $studentgroup->name;
    }
    // if ($tempgroups) {
    $studentdata[] = implode(',', $tempgroups);
    // }
    //Countryfullname($student);
    $studentdata[] = @$countries[$student->country];

    //Instituion
    $studentdata[] = $student->institution;

    //Posts
    $postsql = 'SELECT * FROM {forum_posts} WHERE userid=' . $student->id . ' AND discussion IN ' . $discussionarray . ' AND parent=0';
    if ($starttime) {
        $postsql = $postsql . ' AND created>' . $starttime;
    }
    if ($endtime) {
        $postsql = $postsql . ' AND created<' . $endtime;
    }
    $posts = $DB->get_records_sql($postsql);
    $studentdata[] = count($posts);

    //Replies
    $repsql = 'SELECT * FROM {forum_posts} WHERE userid=' . $student->id . ' AND discussion IN ' . $discussionarray . ' AND parent>0';
    if ($starttime) {
        $repsql = $repsql . ' AND created>' . $starttime;
    }
    if ($endtime) {
        $repsql = $repsql . ' AND created<' . $endtime;
    }
    $replies = $DB->get_records_sql($repsql);
    $studentdata[] = count($replies);
    //BL Customization
    //Unique_activedays
    $postreplysql = 'SELECT * FROM {forum_posts} WHERE userid=' . $student->id . ' AND discussion IN ' . $discussionarray;
    if ($starttime) {
        $postreplysql = $postreplysql . ' AND created>' . $starttime;
    }
    if ($endtime) {
        $postreplysql = $postreplysql . ' AND created<' . $endtime;
    }

    $postreplies = $DB->get_records_sql($postreplysql);
    $uniquepostreply = array();
    foreach ($postreplies as $postreply) {
        $uniquepostreply[] = get_midnight($postreply->created);
    }
    if ($postreplies) {
        $studentdata[] = count(array_unique($uniquepostreply));
    } else {
        $studentdata[] = "0";
    }
    //BL Customization
    //View
    $logtable = 'logstore_standard_log';
    $eventname = '\\\\mod_forum\\\\event\\\\discussion_viewed';
    if ($forumid) {
        $viewsql = "SELECT * FROM {logstore_standard_log} WHERE userid=$student->id AND contextinstanceid=$cm->id AND contextlevel=" . CONTEXT_MODULE . " AND eventname='$eventname'";
    } else {
        $views = $DB->get_records($logtable, array('userid' => $student->id, 'courseid' => $courseid, 'eventname' => $eventname));
        $viewsql = "SELECT * FROM {logstore_standard_log} WHERE userid=$student->id AND courseid=$courseid AND eventname='$eventname'";
    }
    if ($starttime) {
        $viewsql = $viewsql . ' AND timecreated>' . $starttime;
    }
    if ($endtime) {
        $viewsql = $viewsql . ' AND timecreated<' . $endtime;
    }
    $views = $DB->get_records_sql($viewsql);
    $studentdata[] = count($views);
    //BL Customization
    //Unique View days
    $uviewdaysnum = array();
    foreach ($views as $view1) {
        $uviewdaysnum[] = get_midnight($view1->timecreated);
    }
    if ($views) {
        $studentdata[] = count(array_unique($uviewdaysnum));
    } else {
        $studentdata[] = "0";
    }
    //BL Customization
    //Word count
    if ($posts || $replies) {
        $allpostsql = 'SELECT * FROM {forum_posts} WHERE userid=' . $student->id . ' AND discussion IN ' . $discussionarray;
        if ($starttime) {
            $allpostsql = $allpostsql . ' AND created>' . $starttime;
        }
        if ($endtime) {
            $allpostsql = $allpostsql . ' AND created<' . $endtime;
        }
        if ($allposts = $DB->get_records_sql($allpostsql)) {
            $wordcount = 0;
            foreach ($allposts as $post) {
                $wordnum = count_words($post->message);
                $wordcount += $wordnum;
            }
        }
    } else {
        $wordcount = 0;
    }
    $studentdata[] = $wordcount;
    //BL Customization
    //Multimedia
//    $multimedianum = 0;
//    $multimediasql =   "SELECT COUNT(filename) AS filename FROM `mdl_files` INNER JOIN `mdl_forum_posts`
//                        ON mdl_files.itemid = mdl_forum_posts.id WHERE mdl_forum_posts.userid = $student->id
//                        AND NOT mdl_files.filesize = 0  AND  mdl_forum_posts.discussion IN " . $discussionarray;
//    if ($starttime) {
//        $multimediasql = $multimediasql . ' AND timecreated>' . $starttime;
//    }
//    if ($endtime) {
//        $multimediasql = $multimediasql . ' AND timecreated<' . $endtime;
//    }
//    $multimediacount = $DB->get_records_sql($multimediasql);
//    foreach ($multimediacount as $num) {
//        $multimedianum = $num->filename;
//    }
//    $studentdata[] =  $multimedianum;
    //BL Customization
    $multimedianum = 0;
    if ($posts) {
        foreach ($posts as $pdata) {
            $multimedianum += get_mulutimedia_num($pdata->message);
        }
    }
    if ($replies) {
        foreach ($replies as $reply) {
            $multimedianum += get_mulutimedia_num($reply->message);
        }
    }
    $studentdata[] = $multimedianum;

    // Engagement levels
    $engagement = block_forum_report_get_engagement($student->id, $discussionarray);
    $studentdata[] = $engagement->levels[0];
    $studentdata[] = $engagement->levels[1];
    $studentdata[] = $engagement->levels[2];
    $studentdata[] = $engagement->levels[3];
    $studentdata[] = $engagement->average;
    $studentdata[] = $engagement->maximum;

    //First post & Last post
    if ($posts || $replies) {
        $firstpostsql = 'SELECT MIN(created) FROM {forum_posts} WHERE userid=' . $student->id . ' AND discussion IN ' . $discussionarray;
        if ($starttime) {
            $firstpostsql = $firstpostsql . ' AND created>' . $starttime;
        }
        if ($endtime) {
            $firstpostsql = $firstpostsql . ' AND created<' . $endtime;
        }
        $firstpost = $DB->get_record_sql($firstpostsql);
        $minstr = 'min(created)'; //
        $firstpostdate = userdate($firstpost->$minstr);
        $studentdata[] = $firstpostdate;

        $lastpostsql = 'SELECT MAX(created) FROM {forum_posts} WHERE userid=' . $student->id . ' AND discussion IN ' . $discussionarray;
        if ($starttime) {
            $lastpostsql = $lastpostsql . ' AND created>' . $starttime;
        }
        if ($endtime) {
            $lastpostsql = $lastpostsql . ' AND created<' . $endtime;
        }
        $lastpost = $DB->get_record_sql($lastpostsql);
        $maxstr = 'max(created)'; //
        $lastpostdate = userdate($lastpost->$maxstr);
        $studentdata[] = $lastpostdate;
    } else {
        $studentdata[] = '-';
        $studentdata[] = '-';
    }
    $data[] = $studentdata;
}

$csvexport = new \csv_export_writer();
$filename = 'forum-report';
$csvexport->set_filename($filename);
$csvexport->add_data(array(
    'Username', 'Name', 'Group', 'Country', 'Instituion',
    'Posts', 'Replies', 'Unique days active', 'Views', 'Unique days viewed',
    'Word count', 'Multimedia',
    'Engagement#1', 'Engagement#2', 'Engagement#3', 'Engagement#4', 'Average Engagement', 'Maximum Engagement',
    'First post', 'Last post'
));
foreach ($data as $line) {
    $csvexport->add_data($line);
}
$csvexport->download_file();
