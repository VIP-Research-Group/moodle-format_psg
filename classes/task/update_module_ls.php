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
 * This class updates the course module learning style values.
 *
 * @package format_psg
 * @author Ted Krahn
 * @copyright 2022 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_psg\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/psg/lib.php');
require_once($CFG->dirroot . '/blocks/behaviour/locallib.php');

/**
 * The task class.
 *
 * @package format_psg
 * @author Ted Krahn
 * @copyright 2022 Athabasca University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_module_ls extends \core\task\scheduled_task {

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name() {
        return get_string('update', 'format_psg');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $courses = $DB->get_records('course', ['format' => 'psg']);
        foreach ($courses as $course) {

            $modulesls = $this->update_course_modules_ls($course);
            $this->update_student_modules_ls($course, $modulesls);
        }
    }

    /**
     * Calculate and store the course module LS scores.
     *
     * @param stdClass $course The course.
     * @return array
     */
    private function update_course_modules_ls(&$course) {
        global $DB;

        // Get existing module LS records, if any.
        $records = $DB->get_records('format_psg_module_ls', ['courseid' => $course->id]);
        $currentls = [];
        foreach ($records as $r) {
            $currentls[$r->moduleid] = $r->lsc;
        }

        $modules = format_psg_get_course_info($course->id);
        $modulels = [];
        $data = [];

        // Update the module LS records.
        foreach ($modules as $module) {

            // Already has a record.
            if (isset($currentls[$module->id])) {
                $arr = explode(',', $currentls[$module->id]);
                $modulels[$module->id] = [
                    'active' => $arr[0],
                    'reflective' => $arr[1],
                    'sensing' => $arr[2],
                    'intuitive' => $arr[3],
                    'visual' => $arr[4],
                    'verbal' => $arr[5],
                    'sequential' => $arr[6],
                    'global' => $arr[7],
                ];
                unset($currentls[$module->id]);
                continue;
            }

            $lsc = format_psg_get_learning_style($module);
            $modulels[$module->id] = $lsc;

            $lscstr = $lsc['active'] . ',' . $lsc['reflective'] . ',' . $lsc['sensing'] . ',' . $lsc['intuitive'] . ',' .
                    $lsc['visual'] . ',' . $lsc['verbal'] . ',' . $lsc['sequential'] . ',' . $lsc['global'];

            $data[] = (object) [
                'courseid' => $course->id,
                'moduleid' => $module->id,
                'lsc' => $lscstr
            ];
        }
        unset($lsc);

        // Insert data for any new modules.
        if (count($data) > 0) {
            $DB->insert_records('format_psg_module_ls', $data);
        }

        // Remove any data for old modules.
        $params = ['courseid' => $course->id];
        foreach ($currentls as $mid => $lsc) {
            $params['moduleid'] = $mid;
            $DB->delete_records('format_psg_module_ls', $params);
            unset($modulels[$mid]);
        }

        return $modulels;
    }

    /**
     * Calculate and store the student module LS scores.
     *
     * @param stdClass $course The course.
     * @param array $modulels The learning style combinations for the course modules.
     */
    private function update_student_modules_ls(&$course, &$modulels) {
        global $DB;

        $students = [];
        $lsdata = [];

        // Get all the students in this course.
        $roleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $records = block_behaviour_get_participants($course->id, $roleid);
        foreach ($records as $r) {
            $students[$r->id] = $r->id;
        }
        unset($r);

        // And any that might have imported data.
        $records = $DB->get_records('block_behaviour_imported', ['courseid' => $course->id], '', 'distinct userid');
        foreach ($records as $r) {
            $students[$r->userid] = $r->userid;
        }

        // Redo the relevance score for each module for each student.
        $DB->delete_records('format_psg_student_module_ls', ['courseid' => $course->id]);

        foreach ($students as $sid) {

            // For students with ILS responses.
            $slsc = format_psg_get_ls_from_survey($course, $sid);

            if ($slsc) {
                foreach ($modulels as $mid => $mlsc) {
                    $score = format_psg_get_learning_style_score($slsc, $mlsc);

                    // Data for the DB.
                    $lsdata[] = (object) [
                        'courseid' => $course->id,
                        'moduleid' => $mid,
                        'userid' => $sid,
                        'score' => $score,
                        'scoretype' => 0
                    ];
                }
            }

            // For students with common link data.
            list($slsc, $pred) = format_psg_get_ls_from_common_links($course, $sid);

            if ($slsc) {
                foreach ($modulels as $mid => $mlsc) {
                    $score = format_psg_get_learning_style_score($slsc, $mlsc);

                    // Data for the DB.
                    $lsdata[] = (object) [
                        'courseid' => $course->id,
                        'moduleid' => $mid,
                        'userid' => $sid,
                        'score' => $score,
                        'scoretype' => 1
                    ];
                }
            }
        }
        if (count($lsdata) > 0) {
            $DB->insert_records('format_psg_student_module_ls', $lsdata);
        }
    }
}
