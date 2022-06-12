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
 * This file contains main class for the course format Topic
 *
 * @package   format_psg
 * @copyright 2009 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot. '/course/format/lib.php');

/**
 * Main class for the Personalised Study Guide course format
 *
 * @package    format_psg
 * @copyright  2012 Marina Glancy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_psg extends format_base {

    /**
     * Returns true if this course format uses sections
     *
     * @return bool
     */
    public function uses_sections() {
        return true;
    }

    /**
     * Returns the display name of the given section that the course prefers.
     *
     * Use section name is specified by user. Otherwise use default ("Topic #")
     *
     * @param int|stdClass $section Section object from database or just field section.section
     * @return string Display name that the course format prefers, e.g. "Topic 2"
     */
    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string)$section->name !== '') {
            return format_string($section->name, true,
                    array('context' => context_course::instance($this->courseid)));
        } else {
            return $this->get_default_section_name($section);
        }
    }

    /**
     * Returns the default section name for the PSG course format.
     *
     * If the section number is 0, it will use the string with key = section0name from the course format's lang file.
     * If the section number is not 0, the base implementation of format_base::get_default_section_name which uses
     * the string with the key = 'sectionname' from the course format's lang file + the section number will be used.
     *
     * @param stdClass $section Section object from database or just field course_sections section
     * @return string The default value for the section name.
     */
    public function get_default_section_name($section) {
        if ($section->section == 0) {
            // Return the general section.
            return get_string('section0name', 'format_psg');
        } else {
            // Use format_base::get_default_section_name implementation which
            // will display the section name in "Topic n" format.
            return parent::get_default_section_name($section);
        }
    }

    /**
     * The URL to use for the specified course (with section)
     *
     * @param int|stdClass $section Section object from database or just field course_sections.section
     *     if omitted the course view page is returned
     * @param array $options options for view URL. At the moment core uses:
     *     'navigation' (bool) if true and section has no separate page, the function returns null
     *     'sr' (int) used by multipage formats to specify to which section to return
     * @return null|moodle_url
     */
    public function get_view_url($section, $options = array()) {
        global $CFG;
        $course = $this->get_course();
        $url = new moodle_url('/course/view.php', array('id' => $course->id));

        $sr = null;
        if (array_key_exists('sr', $options)) {
            $sr = $options['sr'];
        }
        if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ($sectionno !== null) {
            if ($sr !== null) {
                if ($sr) {
                    $usercoursedisplay = COURSE_DISPLAY_MULTIPAGE;
                    $sectionno = $sr;
                } else {
                    $usercoursedisplay = COURSE_DISPLAY_SINGLEPAGE;
                }
            } else {
                $usercoursedisplay = $course->coursedisplay;
            }
            if ($sectionno != 0 && $usercoursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                $url->param('section', $sectionno);
            } else {
                if (empty($CFG->linkcoursesections) && !empty($options['navigation'])) {
                    return null;
                }
                $url->set_anchor('section-'.$sectionno);
            }
        }
        return $url;
    }

    /**
     * Returns the information about the ajax support in the given source format
     *
     * The returned object's property (boolean)capable indicates that
     * the course format supports Moodle course ajax features.
     *
     * @return stdClass
     */
    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Loads all of the course sections into the navigation
     *
     * @param global_navigation $navigation
     * @param navigation_node $node The course node within the navigation
     */
    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        // If section is specified in course/view.php, make sure it is expanded in navigation.
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }

        // Check if there are callbacks to extend course navigation.
        parent::extend_course_navigation($navigation, $node);

        // We want to remove the general section if it is empty.
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            // The general section is empty to find the navigation node for it we need to get its ID.
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                // We found the node - now remove it.
                $generalsection->remove();
            }
        }
    }

    /**
     * Custom action after section has been moved in AJAX mode
     *
     * Used in course/rest.php
     *
     * @return array This will be passed in ajax respose
     */
    public function ajax_section_move() {
        global $PAGE;
        $titles = array();
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return array('sectiontitles' => $titles, 'action' => 'move');
    }

    /**
     * Returns the list of blocks to be automatically added for the newly created course
     *
     * @return array of default blocks, must contain two keys BLOCK_POS_LEFT and BLOCK_POS_RIGHT
     *     each of values is an array of block names (for left and right side columns)
     */
    public function get_default_blocks() {
        return array(
            BLOCK_POS_LEFT => array(),
            BLOCK_POS_RIGHT => array()
        );
    }

    /**
     * Definitions of the additional options that this course format uses for course
     *
     * PSG format uses the following options:
     * - coursedisplay
     * - hiddensections
     *
     * @param bool $foreditform
     * @return array of options
     */
    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = array(
                'hiddensections' => array(
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ),
                'coursedisplay' => array(
                    'default' => $courseconfig->coursedisplay,
                    'type' => PARAM_INT,
                ),
            );
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseformatoptionsedit = array(
                'hiddensections' => array(
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible')
                        )
                    ),
                ),
                'coursedisplay' => array(
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => array(
                        array(
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi')
                        )
                    ),
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                )
            );
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * This function is called from {@see course_edit_form::definition_after_data()}.
     *
     * @param MoodleQuickForm $mform form the elements are added to.
     * @param bool $forsection 'true' if this is a section edit form, 'false' if this is course edit form.
     * @return array array of references to the added form elements.
     */
    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            // Add "numsections" element to the create course form - it will force new course to be prepopulated
            // with empty sections.
            // The "Number of sections" option is no longer available when editing course, instead teachers should
            // delete and add sections when needed.
            $courseconfig = get_config('moodlecourse');
            $max = (int)$courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }

        return $elements;
    }

    /**
     * Updates format options for a course
     *
     * In case if course format was changed to 'psg', we try to copy options
     * 'coursedisplay' and 'hiddensections' from the previous format.
     *
     * @param stdClass|array $data return value from {@see moodleform::get_data()} or array with data
     * @param stdClass $oldcourse if this function is called from {@see update_course()}
     *     this object contains information about the course before update
     * @return bool whether there were any changes to the options values
     */
    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array)$data;
        if ($oldcourse !== null) {
            $oldcourse = (array)$oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data)) {
                    if (array_key_exists($key, $oldcourse)) {
                        $data[$key] = $oldcourse[$key];
                    }
                }
            }
        }
        return $this->update_format_options($data);
    }

    /**
     * Whether this format allows to delete sections
     *
     * Do not call this function directly, instead use {@see course_can_delete_section()}
     *
     * @param int|stdClass|section_info $section
     * @return bool
     */
    public function can_delete_section($section) {
        return true;
    }

    /**
     * Prepares the templateable object to display section name
     *
     * @param \section_info|\stdClass $section
     * @param bool $linkifneeded
     * @param bool $editable
     * @param null|lang_string|string $edithint
     * @param null|lang_string|string $editlabel
     * @return \core\output\inplace_editable
     */
    public function inplace_editable_render_section_name($section, $linkifneeded = true,
                                                         $editable = null, $edithint = null, $editlabel = null) {
        if (empty($edithint)) {
            $edithint = new lang_string('editsectionname', 'format_psg');
        }
        if (empty($editlabel)) {
            $title = get_section_name($section->course, $section);
            $editlabel = new lang_string('newsectionname', 'format_psg', $title);
        }
        return parent::inplace_editable_render_section_name($section, $linkifneeded, $editable, $edithint, $editlabel);
    }

    /**
     * Indicates whether the course format supports the creation of a news forum.
     *
     * @return bool
     */
    public function supports_news() {
        return true;
    }

    /**
     * Returns whether this course format allows the activity to
     * have "triple visibility state" - visible always, hidden on course page but available, hidden.
     *
     * @param stdClass|cm_info $cm course module (may be null if we are displaying a form for adding a module)
     * @param stdClass|section_info $section section where this module is located or will be added to
     * @return bool
     */
    public function allow_stealth_module_visibility($cm, $section) {
        // Allow the third visibility state inside visible sections or in section 0.
        return !$section->section || $section->visible;
    }

    /**
     * Callback used in WS core_course_edit_section when teacher performs an AJAX action on a section (show/hide).
     *
     * Access to the course is already validated in the WS but the callback has to make sure
     * that particular action is allowed by checking capabilities
     *
     * Course formats should register.
     *
     * @param section_info|stdClass $section
     * @param string $action
     * @param int $sr
     * @return null|array any data for the Javascript post-processor (must be json-encodeable)
     */
    public function section_action($section, $action, $sr) {
        global $PAGE;

        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            // Format 'psg' allows to set and remove markers in addition to common section actions.
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }

        // For show/hide actions call the parent method and return the new content for .section_availability element.
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_psg');
        $rv['section_availability'] = $renderer->section_availability($this->get_section($section));
        return $rv;
    }

    /**
     * Return the plugin configs for external functions.
     *
     * @return array the list of configuration settings
     * @since Moodle 3.5
     */
    public function get_config_for_external() {
        // Return everything (nothing to hide).
        return $this->get_format_options();
    }
}

/**
 * Implements callback inplace_editable() allowing to edit values in-place
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return \core\output\inplace_editable
 */
function format_psg_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            array($itemid, 'psg'), MUST_EXIST);
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}

/**
 * Called to get the module information for a course.
 *
 * @param stdClass $course The DB course table record
 * @return array
 */
function format_psg_get_course_info(&$course) {

    // Get the course module information.
    $modinfo = get_fast_modinfo($course);
    $courseinfo = [];

    foreach ($modinfo->sections as $section) {
        foreach ($section as $cmid) {
            $cm = $modinfo->cms[$cmid];

            // Only want clickable modules.
            if (!$cm->has_view() || !$cm->uservisible) {
                continue;
            }

            $courseinfo[] = $cm;
        }
    }

    return $courseinfo;
}

/**
 * Called to determine whether or not the Behaviour Analytics block is installed in a course.
 *
 * @param int $courseid The course ID.
 * @return boolean
 */
function format_psg_ba_is_installed($courseid) {
    global $DB;

    // Get the courses for which the plugin is installed.
    $sql = "SELECT c.id, c.shortname FROM {course} c
              JOIN {context} ctx ON c.id = ctx.instanceid AND ctx.contextlevel = :contextcourse
             WHERE ctx.id in (SELECT distinct parentcontextid FROM {block_instances}
                               WHERE blockname = 'behaviour')
          ORDER BY c.sortorder";
    $courses = $DB->get_records_sql($sql, array('contextcourse' => CONTEXT_COURSE));

    foreach ($courses as $c) {
        if ($c->id === $courseid) {
            return true;
        }
    }
    return false;
}

/**
 * Called to retrieve the learning style values of a course module.
 *
 * @param int $modid The course module ID.
 * @return array
 */
function format_psg_get_learning_style_from_db($modid) {
    global $DB, $COURSE;

    $params = [
        'courseid' => $COURSE->id,
        'moduleid' => $modid
    ];
    $record = $DB->get_record('format_psg_module_ls', $params);

    if (!$record) {
        $arr = [0, 0, 0, 0, 0, 0, 0, 0];

    } else {
        $arr = explode(',', $record->lsc);
    }

    return [
        'active' => $arr[0],
        'reflective' => $arr[1],
        'sensing' => $arr[2],
        'intuitive' => $arr[3],
        'visual' => $arr[4],
        'verbal' => $arr[5],
        'sequential' => $arr[6],
        'global' => $arr[7],
    ];
}

/**
 * Called to calculate the learning style values of a course module.
 *
 * @param stdClass $mod The course module.
 * @return array
 */
function format_psg_get_learning_style(&$mod) {

    $active = 0;
    $reflective = 0;
    $sensing = 0;
    $intuitive = 0;
    $visual = 0;
    $verbal = 0;
    $sequential = 0;
    $global = 0;

    switch ($mod->modname) {
        case 'assign':
            // Assessments (exams, tests).
            $active = 0.35;
            $reflective = 0.15;
            $sensing = 0.10;
            $intuitive = 0.05;
            $visual = 0.05;
            $verbal = 0.08;
            $sequential = 0.10;
            $global = 0.05;
            break;

        case 'quiz':
            // Self Assessment Exercises.
            $active = 0.25;
            $reflective = 0.11;
            $sensing = 0.07;
            $intuitive = 0.11;
            $visual = 0.11;
            $verbal = 0.16;
            $sequential = 0.09;
            $global = 0.09;
            // Question and answer OR exercise.
            break;

        case 'forum':
            // Forums.
            $active = 0.21;
            $reflective = 0.00;
            $sensing = 0.14;
            $intuitive = 0.14;
            $visual = 0.07;
            $verbal = 0.29;
            $sequential = 0.00;
            $global = 0.14;
            // Group discussion.
            break;

        case 'lti':
            // Educational Software.
            $active = 0.00;
            $reflective = 0.17;
            $sensing = 0.17;
            $intuitive = 0.17;
            $visual = 0.17;
            $verbal = 0.00;
            $sequential = 0.17;
            $global = 0.17;
            break;

        case 'url':
            $tag = format_psg_get_html_tag_url($mod);
            list($active, $reflective, $sensing, $intuitive, $visual, $verbal, $sequential, $global) =
                format_psg_get_tag_ls($tag);
            break;

        case 'book':
            // Narrative Texts.
            $active = 0.02;
            $reflective = 0.33;
            $sensing = 0.11;
            $intuitive = 0.12;
            $visual = 0.02;
            $verbal = 0.30;
            $sequential = 0.09;
            $global = 0.02;
            // Read and solve OR references.
            break;

        case 'page':
            $tag = format_psg_get_html_tag_page($mod);
            list($active, $reflective, $sensing, $intuitive, $visual, $verbal, $sequential, $global) =
                format_psg_get_tag_ls($tag);
            break;

        case 'lesson':
            // Lesson.
            $active = 0.00;
            $reflective = 0.40;
            $sensing = 0.00;
            $intuitive = 0.20;
            $visual = 0.00;
            $verbal = 0.20;
            $sequential = 0.00;
            $global = 0.20;
            break;

        case 'data':
            // Tables.
            $active = 0.08;
            $reflective = 0.32;
            $sensing = 0.20;
            $intuitive = 0.00;
            $visual = 0.16;
            $verbal = 0.16;
            $sequential = 0.00;
            $global = 0.08;
            // Practical facts and materials OR Lists (topics).
            break;

        case 'chat':
            // Chat.
            $active = 0.22;
            $reflective = 0.11;
            $sensing = 0.22;
            $intuitive = 0.22;
            $visual = 0.11;
            $verbal = 0.11;
            $sequential = 0.00;
            $global = 0.00;
            break;

        case 'choice':
            // Multiple Choice Exercises.
            $active = 1.00;
            $reflective = 0.00;
            $sensing = 0.00;
            $intuitive = 0.00;
            $visual = 0.00;
            $verbal = 0.00;
            $sequential = 0.00;
            $global = 0.00;
            break;

        case 'feedback':
            // E-mail.
            $active = 0.22;
            $reflective = 0.00;
            $sensing = 0.11;
            $intuitive = 0.11;
            $visual = 0.11;
            $verbal = 0.33;
            $sequential = 0.00;
            $global = 0.11;
            // Expressions.
            break;

        case 'glossary':
            // Glossaries.
            $active = 0.00;
            $reflective = 0.20;
            $sensing = 0.00;
            $intuitive = 0.27;
            $visual = 0.00;
            $verbal = 0.20;
            $sequential = 0.13;
            $global = 0.20;
            // Definition.
            break;

        case 'survey':
            // Questionnaires.
            $active = 0.25;
            $reflective = 0.13;
            $sensing = 0.18;
            $intuitive = 0.14;
            $visual = 0.00;
            $verbal = 0.25;
            $sequential = 0.13;
            $global = 0.00;
            break;

        case 'wiki':
            // Web pages (wikis).
            $active = 0.10;
            $reflective = 0.20;
            $sensing = 0.10;
            $intuitive = 0.00;
            $visual = 0.10;
            $verbal = 0.20;
            $sequential = 0.15;
            $global = 0.15;
            break;

        case 'workshop':
            // Brainstorming.
            $active = 0.25;
            $reflective = 0.00;
            $sensing = 0.00;
            $intuitive = 0.00;
            $visual = 0.25;
            $verbal = 0.25;
            $sequential = 0.00;
            $global = 0.25;
            // Case Study OR group discussion.
            break;

        case 'scorm':
            break;

        case 'imscp':
            break;

        case 'folder':
            // Real Life Application.
            $active = 0.00;
            $reflective = 0.00;
            $sensing = 0.00;
            $intuitive = 0.00;
            $visual = 0.00;
            $verbal = 0.00;
            $sequential = 0.00;
            $global = 1.00;
            // Practical exercise.
            break;
    }
    return array(
        'active' => $active,
        'reflective' => $reflective,
        'sensing' => $sensing,
        'intuitive' => $intuitive,
        'visual' => $visual,
        'verbal' => $verbal,
        'sequential' => $sequential,
        'global' => $global,
    );
}

/**
 * Function to get the learning style from a dominant html tag.
 *
 * @param string $tag The html tag.
 * @return array
 */
function format_psg_get_tag_ls($tag) {

    $active = 0;
    $reflective = 0;
    $sensing = 0;
    $intuitive = 0;
    $visual = 0;
    $verbal = 0;
    $sequential = 0;
    $global = 0;

    switch ($tag) {
        case 'image':
            $active = 0.05;
            $reflective = 0.05;
            $sensing = 0.18;
            $intuitive = 0.13;
            $visual = 0.44;
            $verbal = 0.05;
            $sequential = 0.03;
            $global = 0.08;
            break;
        case 'video':
            $active = 0.11;
            $reflective = 0.11;
            $sensing = 0.14;
            $intuitive = 0.09;
            $visual = 0.34;
            $verbal = 0.09;
            $sequential = 0.06;
            $global = 0.06;
            break;
        case 'list':
            $active = 0.0;
            $reflective = 0.31;
            $sensing = 0.0;
            $intuitive = 0.15;
            $visual = 0.15;
            $verbal = 0.08;
            $sequential = 0.0;
            $global = 0.23;
            break;
        case 'table':
            $active = 0.08;
            $reflective = 0.32;
            $sensing = 0.2;
            $intuitive = 0.0;
            $visual = 0.16;
            $verbal = 0.16;
            $sequential = 0.0;
            $global = 0.08;
            break;
        case 'email':
            $active = 0.22;
            $reflective = 0.0;
            $sensing = 0.11;
            $intuitive = 0.11;
            $visual = 0.11;
            $verbal = 0.33;
            $sequential = 0.0;
            $global = 0.11;
            break;
        case 'audio':
            $active = 0.0;
            $reflective = 0.0;
            $sensing = 0.2;
            $intuitive = 0.0;
            $visual = 0.0;
            $verbal = 0.8;
            $sequential = 0.0;
            $global = 0.0;
            break;
    }
    return [$active, $reflective, $sensing, $intuitive, $visual, $verbal, $sequential, $global];
}

/**
 * Function to get the dominant tag from html.
 *
 * @param string $html The html to parse.
 * @return string
 */
function format_psg_get_html_tag(&$html) {

    $dom = new \DOMDocument();
    @$dom->loadHTML($html);

    $tags = [
        'img' => 0,
        'video' => 0,
        'ul' => 0,
        'ol' => 0,
        'table' => 0,
        'email' => 0,
        'audio' => 0
    ];

    foreach ($tags as $tag => $value) {
        foreach ($dom->getElementsByTagName($tag) as $t) {
            $tags[$tag] += 1;
        }
    }

    $tags['image'] = $tags['img'];
    unset($tags['img']);

    $tags['list'] = $tags['ul'] + $tags['ol'];
    unset($tags['ul']);
    unset($tags['ol']);

    $maxtag = 'unknown';
    $max = 0;
    foreach ($tags as $tag => $value) {
        if ($value > $max) {
            $maxtag = $tag;
            $max = $value;
        }
    }

    return $maxtag;
}

/**
 * Function to get tags from a page type learning activity.
 *
 * @param stdClass $cm The course module.
 * @return string
 */
function format_psg_get_html_tag_page(&$cm) {
    global $DB;

    $page = $DB->get_record('page', array('id' => $cm->instance), '*', MUST_EXIST);

    return format_psg_get_html_tag($page->content);
}

/**
 * Function to get tags from a url type learning activity.
 *
 * @param stdClass $cm The course module.
 * @return string
 */
function format_psg_get_html_tag_url(&$cm) {
    global $DB;

    $url = $DB->get_record('url', array('id' => $cm->instance), '*', MUST_EXIST);

    @$contents = file_get_contents($url->externalurl);
    if ($contents === false) {
        return 'unkown';
    }

    return format_psg_get_html_tag($contents);
}

/**
 * Called to calculate the learning style score of a course module
 * in comaprison to a student's learning style.
 *
 * @param array $lsc The student's learning style combination.
 * @param array $module The course module learning style.
 * @return int
 */
function format_psg_get_learning_style_score(&$lsc, &$module) {

    $score = 0;

    if (!$lsc) {
        return $score;
    }

    // Determine if learning style of module and student align.
    if ($lsc['active'] > $lsc['reflective'] &&
        $module['active'] > $module['reflective']) {
        $score += 1;

    } else if ($lsc['active'] < $lsc['reflective'] &&
               $module['active'] < $module['reflective']) {
        $score += 1;

    } else if ($lsc['active'] == $lsc['reflective'] &&
               $module['active'] == $module['reflective']) {
        $score += 1;
    }

    if ($lsc['sensing'] > $lsc['intuitive'] &&
        $module['sensing'] > $module['intuitive']) {
        $score += 1;

    } else if ($lsc['sensing'] < $lsc['intuitive'] &&
               $module['sensing'] < $module['intuitive']) {
        $score += 1;

    } else if ($lsc['sensing'] == $lsc['intuitive'] &&
               $module['sensing'] == $module['intuitive']) {
        $score += 1;
    }

    if ($lsc['visual'] > $lsc['verbal'] &&
        $module['visual'] > $module['verbal']) {
        $score += 1;

    } else if ($lsc['visual'] < $lsc['verbal'] &&
               $module['visual'] < $module['verbal']) {
        $score += 1;

    } else if ($lsc['visual'] == $lsc['verbal'] &&
               $module['visual'] == $module['verbal']) {
        $score += 1;
    }

    if ($lsc['sequential'] > $lsc['global'] &&
        $module['sequential'] > $module['global']) {
        $score += 1;

    } else if ($lsc['sequential'] < $lsc['global'] &&
               $module['sequential'] < $module['global']) {
        $score += 1;

    } else if ($lsc['sequential'] == $lsc['global'] &&
               $module['sequential'] == $module['global']) {
        $score += 1;
    }

    return $score;
}

/**
 * Called to get a prediction of a student's learning style based on the
 * common links graph for the student generated by Behaviour Analytics.
 *
 * @param stdClass $course The course.
 * @param int $userid The student ID.
 * @return array
 */
function format_psg_get_ls_from_common_links(&$course, $userid) {
    global $DB;

    $prediction = [];
    // Was script called with course id where plugin is installed?
    if (format_psg_ba_is_installed($course->id)) {

        // Get values of analysis to be used for prediction.
        $installed = $DB->get_record('block_behaviour_installed', array('courseid' => $course->id));
        $prediction = explode('_', $installed->prediction);
    }

    $min = null;
    $manual = false;
    if (count($prediction) == 3) {

        // Get the latest membership data for this student.
        $params = array(
            'courseid' => $course->id,
            'userid' => $prediction[0],
            'coordsid' => $prediction[1],
            'clusterid' => $prediction[2],
            'studentid' => $userid,
        );

        // Had to do it this way to get it to work on both Mysql and Postgresql??
        $fields = 'min(iteration) as iteration';
        $min = $DB->get_record('block_behaviour_man_members', $params, $fields);
        if ($min->iteration) {
            $params['iteration'] = $min->iteration;
            $min = $DB->get_record('block_behaviour_man_members', $params);
            $manual = true;

        } else {
            $min = $DB->get_record('block_behaviour_members', $params, $fields);
            $params['iteration'] = $min->iteration;
            $min = $DB->get_record('block_behaviour_members', $params);
        }
    }

    $lsc = null;

    if ($min) {
        // Get the course module information.
        $courseinfo = format_psg_get_course_info($course);
        $modinfo = [];
        $nodes = [];

        foreach ($courseinfo as $ci) {
            $modinfo[$ci->id] = $ci;
            $nodes[$ci->id] = 0;
        }

        // Get the common links for the cluster.
        unset($params['studentid']);
        $params['clusternum'] = $min->clusternum;

        if ($manual) {
            $records = $DB->get_records('block_behaviour_man_cmn_link', $params, '', 'id, link, weight');
        } else {
            $records = $DB->get_records('block_behaviour_common_links', $params, '', 'id, link, weight');
        }

        if (count($records)) {
            // Weight the clicks.
            foreach ($records as $r) {

                $arr = explode('_', $r->link);
                if (!isset($nodes[$arr[0]]) || !isset($nodes[$arr[1]])) {
                    continue;
                }
                if ($arr[0] != $arr[1]) {
                    $nodes[$arr[0]] += $r->weight;
                }
                $nodes[$arr[1]] += $r->weight;
            }

            // Determine the student's learning style values from their common links.
            $lsc = array(
                'active' => 0,
                'reflective' => 0,
                'sensing' => 0,
                'intuitive' => 0,
                'visual' => 0,
                'verbal' => 0,
                'sequential' => 0,
                'global' => 0,
            );

            foreach ($nodes as $k => $v) {
                $ls = format_psg_get_learning_style_from_db($modinfo[$k]->id);

                $lsc['active'] += $ls['active'] * $v;
                $lsc['reflective'] += $ls['reflective'] * $v;
                $lsc['visual'] += $ls['visual'] * $v;
                $lsc['verbal'] += $ls['verbal'] * $v;
                $lsc['sensing'] += $ls['sensing'] * $v;
                $lsc['intuitive'] += $ls['intuitive'] * $v;
                $lsc['sequential'] += $ls['sequential'] * $v;
                $lsc['global'] += $ls['global'] * $v;
            }
        }
    }
    return [ $lsc, $prediction ];
}

/**
 * Called to get the learning style from the ILS survey in Behaviour Analytics.
 *
 * @param stdClass $course The course.
 * @param int $userid The student ID.
 * @return array
 */
function format_psg_get_ls_from_survey(&$course, $userid) {
    global $DB;

    $lsc = null;

    // Was script called with course id where plugin is installed?
    if (!format_psg_ba_is_installed($course->id)) {
        return $lsc;
    }

    $title = get_string('ilstitle', 'block_behaviour');
    $surveyid = $DB->get_record('block_behaviour_surveys', ['title' => $title])->id;

    $params = [
        'courseid' => $course->id,
        'studentid' => $userid,
        'surveyid' => $surveyid,
    ];
    $responses = $DB->get_records('block_behaviour_survey_rsps', $params, 'attempt DESC, qorder');

    if (count($responses) == 0) {
        return $lsc;
    }

    $lsc = array(
        'active' => 0,
        'reflective' => 0,
        'sensing' => 0,
        'intuitive' => 0,
        'visual' => 0,
        'verbal' => 0,
        'sequential' => 0,
        'global' => 0,
    );
    $n = 0;
    $m = 0;

    // Calculate the survey results.
    foreach ($responses as $r) {

        if ($n === 0) {
            if (intval($r->response)) {
                $lsc['reflective'] += 1;
            } else {
                $lsc['active'] += 1;
            }

        } else if ($n === 1) {
            if (intval($r->response)) {
                $lsc['intuitive'] += 1;
            } else {
                $lsc['sensing'] += 1;
            }

        } else if ($n === 2) {
            if (intval($r->response)) {
                $lsc['verbal'] += 1;
            } else {
                $lsc['visual'] += 1;
            }

        } else if ($n === 3) {
            if (intval($r->response)) {
                $lsc['global'] += 1;
            } else {
                $lsc['sequential'] += 1;
            }
        }
        if ($n === 3) {
            $n = 0;
        } else {
            $n++;
        }
        if ($m++ >= 43) {
            break;
        }
    }

    // Determine the dominant category and value.
    if ($lsc['active'] > $lsc['reflective']) {
        $lsc['active'] -= $lsc['reflective'];
        $lsc['reflective'] = 0;
    } else {
        $lsc['reflective'] -= $lsc['active'];
        $lsc['active'] = 0;
    }

    if ($lsc['sensing'] > $lsc['intuitive']) {
        $lsc['sensing'] -= $lsc['intuitive'];
        $lsc['intuitive'] = 0;
    } else {
        $lsc['intuitive'] -= $lsc['sensing'];
        $lsc['sensing'] = 0;
    }

    if ($lsc['visual'] > $lsc['verbal']) {
        $lsc['visual'] -= $lsc['verbal'];
        $lsc['verbal'] = 0;
    } else {
        $lsc['verbal'] -= $lsc['visual'];
        $lsc['visual'] = 0;
    }

    if ($lsc['sequential'] > $lsc['global']) {
        $lsc['sequential'] -= $lsc['global'];
        $lsc['global'] = 0;
    } else {
        $lsc['global'] -= $lsc['sequential'];
        $lsc['sequential'] = 0;
    }

    return $lsc;
}
