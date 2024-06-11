<?php

defined('MOODLE_INTERNAL') or die();

function xmldb_block_forum_report_upgrade($oldversion)
{
    /** @var \moodle_database $DB */
    global $DB;

    $dbmanager = $DB->get_manager();

    if ($oldversion < 2024061100)
    {
        if ($dbmanager->table_exists('forum_report_results')) {
            $dbmanager->drop_table(new xmldb_table('forum_report_results'));
        }
        if ($dbmanager->table_exists('forum_report_schedules')) {
            $dbmanager->drop_table(new xmldb_table('forum_report_schedules'));
        }
        $dbmanager->install_from_xmldb_file(__DIR__ . '/install.xml');
        upgrade_block_savepoint(true, 2024061100, 'forum_report');
    }
    
    return true;
}
