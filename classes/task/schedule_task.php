<?php

namespace block_forum_report\task;

class schedule_task extends \core\task\scheduled_task {
    public function get_name() {
        return 'Forum Report Schedule Task';
    }

    public function execute() {
        mtrace("Hello World!");
    }
};
