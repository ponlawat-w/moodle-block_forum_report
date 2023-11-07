<?php

namespace block\forum_report;

require_once(__DIR__ . '/../reportlib.php');

class permission_test extends \advanced_testcase {
    private function getroleid($archetype) {
        /** @var \moodle_database $DB */
        global $DB;
        return $DB->get_record('role', ['archetype' => $archetype], 'id', MUST_EXIST)->id;
    }

    /**
     * @param callable(): void $action
     * @return void
     */
    private function assertThrows($action) {
        try {
            $action();
        } catch (\Exception $_) {
            return $this->assertTrue(true);
        }
        $this->assertTrue(false, 'Action did not throw any exceptions, expected to throw.');
    }

    /**
     * @param callable(): void $action
     * @return void
     */
    private function assertNotThrows($action) {
        $action();
        $this->assertTrue(true, 'Action threw an exception, expected none.');
    }

    public function test_viewpermissions() {
        $this->resetAfterTest(true);

        $dataGenerator = $this->getDataGenerator();
        $course = $dataGenerator->create_course();
        $coursecontext = \core\context\course::instance($course->id);
        $manager = $dataGenerator->create_user();
        $teacher = $dataGenerator->create_user();
        $student = $dataGenerator->create_user();

        $dataGenerator->enrol_user($manager->id, $course->id, $this->getroleid('manager'));
        $dataGenerator->enrol_user($teacher->id, $course->id, $this->getroleid('teacher'));
        $dataGenerator->enrol_user($student->id, $course->id, $this->getroleid('student'));

        $block = $dataGenerator->create_block('forum_report', ['parentcontextid' => $coursecontext->id]);

        $this->setUser($manager);
        $this->assertNotThrows(function() use ($course) {
            block_forum_report_checkpermission($course->id, 0);
        });

        $this->setUser($teacher);
        $this->assertThrows(function() use ($course) {
            block_forum_report_checkpermission($course->id, 0);
        });

        $this->setUser($student);
        $this->assertThrows(function() use ($course) {
            block_forum_report_checkpermission($course->id, 0);
        });

        assign_capability('block/forum_report:view', CAP_ALLOW, $this->getroleid('student'), \core\context\block::instance($block->id)->id, true);
        assign_capability('block/forum_report:viewothergroups', CAP_ALLOW, $this->getroleid('student'), \core\context\block::instance($block->id)->id, true);

        $this->setUser($student);
        $this->assertNotThrows(function() use ($course) {
            block_forum_report_checkpermission($course->id, 0);
        });
    }

    public function test_viewgrouppermissions() {
        $this->resetAfterTest(true);

        $dataGenerator = $this->getDataGenerator();
        $course = $dataGenerator->create_course();
        $coursecontext = \core\context\course::instance($course->id);
        $manager = $dataGenerator->create_user();
        $teacher1 = $dataGenerator->create_user();
        $teacher2 = $dataGenerator->create_user();
        $student1 = $dataGenerator->create_user();
        $student2 = $dataGenerator->create_user();
        
        $group1 = $dataGenerator->create_group(['courseid' => $course->id]);
        $group2 = $dataGenerator->create_group(['courseid' => $course->id]);

        $dataGenerator->enrol_user($manager->id, $course->id, $this->getroleid('manager'));
        $dataGenerator->enrol_user($teacher1->id, $course->id, $this->getroleid('teacher'));
        $dataGenerator->enrol_user($teacher2->id, $course->id, $this->getroleid('teacher'));
        $dataGenerator->enrol_user($student1->id, $course->id, $this->getroleid('student'));
        $dataGenerator->enrol_user($student2->id, $course->id, $this->getroleid('student'));

        $dataGenerator->create_group_member(['userid' => $teacher1->id, 'groupid' => $group1->id]);
        $dataGenerator->create_group_member(['userid' => $student1->id, 'groupid' => $group1->id]);
        $dataGenerator->create_group_member(['userid' => $teacher2->id, 'groupid' => $group2->id]);
        $dataGenerator->create_group_member(['userid' => $student2->id, 'groupid' => $group2->id]);

        $block = $dataGenerator->create_block('forum_report', ['parentcontextid' => $coursecontext->id]);

        $this->setUser($manager);
        $this->assertNotThrows(function() use ($course) {
            block_forum_report_checkpermission($course->id, 0);
        });

        $this->setUser($teacher1);
        $this->assertThrows(function() use ($course) {
            block_forum_report_checkpermission($course->id, 0);
        });
        $this->assertNotThrows(function() use ($course, $group1) {
            block_forum_report_checkpermission($course->id, $group1->id);
        });
        $this->assertThrows(function() use ($course, $group2) {
            block_forum_report_checkpermission($course->id, $group2->id);
        });

        $this->setUser($teacher2);
        $this->assertThrows(function() use ($course) {
            block_forum_report_checkpermission($course->id, 0);
        });
        $this->assertThrows(function() use ($course, $group1) {
            block_forum_report_checkpermission($course->id, $group1->id);
        });
        $this->assertNotThrows(function() use ($course, $group2) {
            block_forum_report_checkpermission($course->id, $group2->id);
        });

        $this->setUser($student1);
        $this->assertThrows(function() use ($course) {
            block_forum_report_checkpermission($course->id, 0);
        });
        $this->assertThrows(function() use ($course, $group1) {
            block_forum_report_checkpermission($course->id, $group1->id);
        });
        $this->assertThrows(function() use ($course, $group2) {
            block_forum_report_checkpermission($course->id, $group2->id);
        });

        $this->setUser($student2);
        $this->assertThrows(function() use ($course) {
            block_forum_report_checkpermission($course->id, 0);
        });
        $this->assertThrows(function() use ($course, $group1) {
            block_forum_report_checkpermission($course->id, $group1->id);
        });
        $this->assertThrows(function() use ($course, $group2) {
            block_forum_report_checkpermission($course->id, $group2->id);
        });

        assign_capability('block/forum_report:viewothergroups', CAP_ALLOW, $this->getroleid('teacher'), \core\context\block::instance($block->id)->id, true);

        $this->setUser($teacher1);
        $this->assertNotThrows(function() use ($course) {
            block_forum_report_checkpermission($course->id, 0);
        });
        $this->assertNotThrows(function() use ($course, $group1) {
            block_forum_report_checkpermission($course->id, $group1->id);
        });
        $this->assertNotThrows(function() use ($course, $group2) {
            block_forum_report_checkpermission($course->id, $group2->id);
        });

        $this->setUser($teacher2);
        $this->assertNotThrows(function() use ($course) {
            block_forum_report_checkpermission($course->id, 0);
        });
        $this->assertNotThrows(function() use ($course, $group1) {
            block_forum_report_checkpermission($course->id, $group1->id);
        });
        $this->assertNotThrows(function() use ($course, $group2) {
            block_forum_report_checkpermission($course->id, $group2->id);
        });
    }
}
