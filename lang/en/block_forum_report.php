<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Forum reports for virtual exchange.
 * @package   vexforum
 * @copyright 2017 Takahiro Nakahara
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Forum Report';

$string['executionschedule'] = 'Execution Schedule';
$string['executionschedule_help'] = 'Numbers of time in 24-hour format to execute schedule, separated with comma (,). For example, "3, 15" for 3AM and 3PM of everyday.';

$string['selectforum'] = 'Select forum';
$string['alloncourse'] = 'All on this course';
$string['allmygroups'] = 'All of my groups';
$string['replies'] = 'Replies';
$string['el1'] = '1st Engagement';
$string['el2'] = '2nd Engagement';
$string['el3'] = '3rd Engagement';
$string['el4up'] = '4th+ Engagement';
$string['elavg'] = 'Average Engagement Level';
$string['elmax'] = 'Maximum Engagement Level';
$string['firstpost'] = 'First Post';
$string['lastpost'] = 'Last Post';
$string['sendreminder'] = 'Send reminder';
$string['completereport'] = 'Complete report';
$string['views'] = 'Views';
$string['wordcount'] = 'Word count';
$string['reportfilter'] = 'Report filter';
$string['showreport'] = 'Show report';
$string['multimedia'] = 'Multimedia';
$string['multimedia_image'] = 'Images';
$string['multimedia_video'] = 'Videos';
$string['multimedia_audio'] = 'Audios';
$string['multimedia_link'] = 'Links';
$string['reportstart'] = 'Start';
$string['reportend'] = 'End';
//BL Customized Code -->>
$string['uniqueview'] = 'Unique days viewed';
$string['uniqueactive'] = 'Unique days active';
$string['perpage'] = 'No. of records per page';
//BL Customized Code <<--

$string['getinstantreport'] = 'Get instant report result (with out being scheduled)';

$string['requestnewreport'] = 'Request a new report';
$string['myrequestedreports'] = 'My Requested Reports';
$string['requestedtime'] = 'Requested Time';
$string['scheduledtime'] = 'Scheduled Time';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['view'] = 'View';
$string['delete'] = 'Delete';
$string['nodata'] = 'There are no data to display.';
$string['status_scheduled'] = 'Scheduled';
$string['status_executing'] = 'Running…';
$string['status_error'] = 'Failed';
$string['status_finish'] = 'Finished';
$string['status_manual'] = 'Manual Run';

$string['reportschedule'] = 'Report schedule';
$string['createdby'] = 'Created by';
$string['emptyresults'] = 'This report request has no reults. Please try again with a different configuration.';

//Reminder mail
$string['remindsubject'] = 'Reminder to participate in the international exchange';
$string['remindmessage'] = 'We\'ve noticed you haven\'t been participating in the international online exchange. Please log in and reply to others using the forums. Good luck!';
$string['sentreminder'] = 'Sent a reminder.';

$string['forum_report:sendreminder'] = 'Send reminder';
$string['forum_report:addinstance'] = 'Add a new forum report block';
$string['forum_report:view'] = 'View forum report';
$string['forum_report:viewothergroups'] = 'View forum report of users from other groups';
$string['forum_report:getinstantreport'] = 'Get report results instantly without being scheduled';

$string['deleteconfirmation_title'] = 'Report Delete Confirmation';
$string['deleteconfirmation_description'] = 'Are you sure you want to delete this report?';

$string['engagement_method'] = 'Engagement method';
$string['engagement_method_help'] = '<p>Engagement Calculation Method</p><strong>Person-to-Person Engagement:</strong> The engagement level increases each time a user replies to the same user in the same thread.<br><strong>Thread Total Count Engagement:</strong> The engagement level increases each time a user participate in the same thread.<br><strong>Thread Engagement:</strong> The engagement level increases each time a user participates in a reply where they already participated in the parent posts.';
$string['engagement_persontoperson'] = 'Person-to-Person Engagement';
$string['engagement_persontoperson_description'] = 'The engagement level increases each time a user replies to the same user in the same thread.';
$string['engagement_threadtotalcount'] = 'Thread Total Count Engagement';
$string['engagement_threadtotalcount_description'] = 'The engagement level increases each time a user participate in the same thread.';
$string['engagement_threadengagement'] = 'Thread Engagement';
$string['engagement_threadengagement_description'] = 'The engagement level increases each time a user participates in a reply where they already participated in the parent posts.';

$string['engagement_admin_defaultmethod'] = 'Default Engagement Calculation Method';

$string['engagement_international'] = 'International engagement only';

$string['reactionsreceived'] = 'Reactions Received';
$string['reactionsgiven'] = 'Reactions Given';
