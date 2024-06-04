<?php

namespace block_forum_report\task;

require_once(__DIR__ . '/../../lib.php');

class schedule_task extends \core\task\scheduled_task {
    public function get_name() {
        return 'Forum Report Schedule Task';
    }

    private function executeschedule($schedule) {
        /** @var \moodle_database $DB */
        global $DB;

        try {
            $schedule->status = BLOCK_FORUM_REPORT_STATUS_EXECUTING;
            $schedule->processedtime = time();
            $DB->update_record('forum_report_schedules', $schedule);

            $DB->execute(<<<SQL
                DELETE FROM {forum_report_results} WHERE schedule IN (
                    SELECT id FROM {forum_report_schedules} WHERE userid = ? AND createdtime < ?
                )
            SQL, [$schedule->userid, $schedule->createdtime]);
            $DB->execute(<<<SQL
                DELETE FROM {forum_report_schedules} WHERE userid = ? AND createdtime < ?
            SQL, [$schedule->userid, $schedule->createdtime]);

            block_forum_report_executeschedule($schedule);

            $schedule->status = BLOCK_FORUM_REPORT_STATUS_FINISH;
            $schedule->processedtime = time();
            $DB->update_record('forum_report_schedules', $schedule);
            return true;
        } catch (\Throwable $ex) {
            $schedule->status = BLOCK_FORUM_REPORT_STATUS_ERROR;
            $schedule->message = $ex->getMessage();
            $schedule->processedtime = time();
            $DB->update_record('forum_report_schedules', $schedule);
            return false;
        }
    }

    public function execute() {
        /** @var \moodle_database $DB */
        global $DB;

        if (block_forum_report_getnextscheduledtime() > time()) return;

        $schedules = $DB->get_records('forum_report_schedules', ['status' => BLOCK_FORUM_REPORT_STATUS_SCHEDULED]);
        foreach ($schedules as $schedule) {
            mtrace('Executing forum report schedule ID: ' . $schedule->id);
            $success = $this->executeschedule($schedule);
            mtrace($success ? 'Success' : 'Failed');
        }

        set_config('lastexecution', time(), 'block_forum_report');
    }
};
