<?php

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'block_forum_report\\task\\schedule_task',
        'blocking' => 0,
        'minute' => '0',
        'hour' => '*',
        'day' => '*',
        'month' => '*',
        'dayofweek' => '*'
    ]
];
