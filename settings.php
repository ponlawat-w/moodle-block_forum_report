<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin administration pages are defined here.
 *
 * @package     block_forum_report
 * @category    admin
 * @copyright   2023 Ponlawat Weerapanpisit <ponlawat_w@outlook.co.th>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/classes/engagement.php');

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'block_forum_report/executionschedule',
        get_string('executionschedule', 'block_forum_report'),
        get_string('executionschedule_help', 'block_forum_report'),
        '4, 10, 16, 22'
    ));

    $settings->add(new admin_setting_configselect(
        'block_forum_report/defaultengagementmethod',
        get_string('engagement_admin_defaultmethod', 'block_forum_report'),
        get_string('engagement_method_help', 'block_forum_report'),
        \block_forum_report\engagement::THREAD_ENGAGEMENT, \block_forum_report\engagement::getselectoptions()
    ));
}
