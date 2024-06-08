<?php

namespace block_forum_report\task;

require_once(__DIR__ . '/../../lib.php');

class schedule_task extends \core\task\scheduled_task {
    public function get_name() {
        return 'Forum Report Schedule Task';
    }

    public function execute() {
        /** @var \moodle_database $DB */
        global $DB;

        if (block_forum_report_getnextscheduledtime() > time()) return;

        $schedules = $DB->get_records('forum_report_schedules', ['status' => BLOCK_FORUM_REPORT_STATUS_SCHEDULED]);
        foreach ($schedules as $schedule) {
            mtrace('Executing forum report schedule ID: ' . $schedule->id);
            $success = block_forum_report_executeschedule($schedule);
            mtrace($success ? 'Success' : 'Failed');
        }

        set_config('lastexecution', time(), 'block_forum_report');
    }
};
