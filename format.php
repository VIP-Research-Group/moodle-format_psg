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
 * Personalised Study Guide course format, altered from the topics format.
 *
 * @package format_psg
 * @copyright 2006 The Open University
 * @author N.D.Freear@open.ac.uk, and others.
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/completionlib.php');

// Horrible backwards compatible parameter aliasing.
if ($topic = optional_param('topic', 0, PARAM_INT)) {
    $url = $PAGE->url;
    $url->param('section', $topic);
    debugging('Outdated topic param passed to course/view.php', DEBUG_DEVELOPER);
    redirect($url);
}
// End backwards-compatible aliasing.

$context = context_course::instance($course->id);
// Retrieve course format option fields and add them to the $course object.
$course = course_get_format($course)->get_course();

if (($marker >= 0) && has_capability('moodle/course:setcurrentsection', $context) && confirm_sesskey()) {
    $course->marker = $marker;
    course_set_marker($course->id, $marker);
}

// Check to see if the student turned on/off the personalisation, default is on.
$psgon = true;
$params = [
    'courseid' => $course->id,
    'userid' => $USER->id,
];
$records = $DB->get_records('block_behaviour_psg_log', $params, 'time DESC');

$record = reset($records);

if ($record && !$record->psgon) {
    $lsc = null;
    $psgon = false;

} else if (get_config('format_psg', 'useils')) {
    $lsc = format_psg_get_ls_from_survey($course, $USER->id);

} else {
    list($lsc, $prediction) = format_psg_get_ls_from_common_links($course, $USER->id);

    if (count($prediction) == 3 && !$lsc) { // Then this student is not clustered, no error msg.
        $psgon = false;
    }
}

// Make sure section 0 is created.
course_create_sections_if_missing($course, 0);
$renderer = $PAGE->get_renderer('format_psg');

// Display the course page.
if (!empty($displaysection)) {
    $renderer->print_one_section_page($course, $displaysection, $lsc, $psgon);
} else {
    $renderer->print_multiple_sections_page($course, $lsc, $psgon);
}

// Include course format js module.
$PAGE->requires->js('/course/format/psg/format.js');
