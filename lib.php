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
 * Learning journey course format: topic-style sections with optional start/end availability.
 *
 * @package    format_learningjourney
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/course/format/lib.php');

use core\output\inplace_editable;
use core_courseformat\base as course_format_base;
use lang_string;
use section_info;

/**
 * Course format class.
 */
class format_learningjourney extends course_format_base {

    /** @var string File area for optional section banner/cover image ({@see format_learningjourney_pluginfile()}). */
    public const FILEAREA_SECTION_IMAGE = 'sectionimage';

    /**
     * File manager options for {@link self::FILEAREA_SECTION_IMAGE}.
     *
     * @return array
     */
    public static function section_image_file_options(): array {
        global $CFG;
        return [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => $CFG->maxbytes,
            'accepted_types' => ['image'],
        ];
    }

    /**
     * Whether "today" for the current user falls inside the section schedule.
     *
     * Comparison uses the calendar day in the user's timezone (not the exact time of day):
     * - start: first enabled day is the calendar day of the stored start timestamp;
     * - end: last enabled day is the calendar day of the stored end timestamp.
     * If either date is unset, that bound is treated as open.
     */
    public function is_section_within_schedule(section_info $section): bool {
        if ((int) $section->section === 0) {
            return true;
        }
        $start = (int) ($section->tjstart ?? 0);
        $end = (int) ($section->tjend ?? 0);
        if ($start <= 0 && $end <= 0) {
            return true;
        }

        $now = time();
        $todaykey = (int) userdate($now, '%Y%m%d', 99);
        if ($start > 0) {
            $startdaykey = (int) userdate($start, '%Y%m%d', 99);
            if ($todaykey < $startdaykey) {
                return false;
            }
        }
        if ($end > 0) {
            $enddaykey = (int) userdate($end, '%Y%m%d', 99);
            if ($todaykey > $enddaykey) {
                return false;
            }
        }
        return true;
    }

    /**
     * Hide sections outside the learning-journey date window for anyone who does not bypass schedule rules.
     */
    public function is_section_visible(section_info $section): bool {
        global $USER;
        if ((int) $section->section !== 0 && !$this->user_bypasses_section_schedule((int) $USER->id)) {
            if (!$this->is_section_within_schedule($section)) {
                return false;
            }
        }
        return parent::is_section_visible($section);
    }

    /**
     * Teachers and managers bypass date restrictions.
     */
    protected function user_bypasses_section_schedule(int $userid): bool {
        if ($userid <= 0) {
            return false;
        }
        $context = $this->get_context();
        return has_capability('moodle/course:update', $context, $userid);
    }

    public function section_get_available_hook(section_info $section, &$available, &$availableinfo): void {
        if (!$available) {
            return;
        }
        $userid = $section->modinfo->get_user_id();
        if ($this->user_bypasses_section_schedule($userid)) {
            return;
        }
        if ($this->is_section_within_schedule($section)) {
            return;
        }
        $available = false;
        $availableinfo = '';
    }

    public function uses_sections(): bool {
        return true;
    }

    public function uses_course_index(): bool {
        return true;
    }

    public function uses_indentation(): bool {
        return (bool) get_config('format_learningjourney', 'indentation');
    }

    public function get_section_name($section) {
        $section = $this->get_section($section);
        if ((string) $section->name !== '') {
            return format_string($section->name, true,
                ['context' => context_course::instance($this->courseid)]);
        }
        return $this->get_default_section_name($section);
    }

    public function get_default_section_name($section) {
        $section = $this->get_section($section);
        if ($section->sectionnum == 0) {
            return get_string('section0name', 'format_learningjourney');
        }
        return get_string('newsection', 'format_learningjourney');
    }

    public function page_title(): string {
        return get_string('sectionoutline', 'format_learningjourney');
    }

    public function get_view_url($section, $options = []) {
        $course = $this->get_course();
        if (array_key_exists('sr', $options) && !is_null($options['sr'])) {
            $sectionno = $options['sr'];
        } else if (is_object($section)) {
            $sectionno = $section->section;
        } else {
            $sectionno = $section;
        }
        if ((!empty($options['navigation']) || array_key_exists('sr', $options)) && $sectionno !== null) {
            $sectioninfo = $this->get_section($sectionno);
            return new moodle_url('/course/section.php', ['id' => $sectioninfo->id]);
        }
        return new moodle_url('/course/view.php', ['id' => $course->id]);
    }

    public function supports_ajax() {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    public function supports_components(): bool {
        return true;
    }

    public function extend_course_navigation($navigation, navigation_node $node) {
        global $PAGE;
        if ($navigation->includesectionnum === false) {
            $selectedsection = optional_param('section', null, PARAM_INT);
            if ($selectedsection !== null && (!defined('AJAX_SCRIPT') || AJAX_SCRIPT == '0') &&
                    $PAGE->url->compare(new moodle_url('/course/view.php'), URL_MATCH_BASE)) {
                $navigation->includesectionnum = $selectedsection;
            }
        }
        parent::extend_course_navigation($navigation, $node);
        $modinfo = get_fast_modinfo($this->get_course());
        $sections = $modinfo->get_sections();
        if (!isset($sections[0])) {
            $section = $modinfo->get_section_info(0);
            $generalsection = $node->get($section->id, navigation_node::TYPE_SECTION);
            if ($generalsection) {
                $generalsection->remove();
            }
        }
    }

    public function ajax_section_move() {
        global $PAGE;
        $titles = [];
        $course = $this->get_course();
        $modinfo = get_fast_modinfo($course);
        $renderer = $this->get_renderer($PAGE);
        if ($renderer && ($sections = $modinfo->get_section_info_all())) {
            foreach ($sections as $number => $section) {
                $titles[$number] = $renderer->section_title($section, $course);
            }
        }
        return ['sectiontitles' => $titles, 'action' => 'move'];
    }

    public function get_default_blocks() {
        return [
            BLOCK_POS_LEFT => [],
            BLOCK_POS_RIGHT => [],
        ];
    }

    public function section_format_options($foreditform = false) {
        static $sectionformatoptions = false;
        if ($sectionformatoptions === false) {
            $sectionformatoptions = [
                'tjstart' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
                'tjend' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
            ];
        }
        if ($foreditform && !isset($sectionformatoptions['tjstart']['label'])) {
            $sectionformatoptionsedit = [
                'tjstart' => [
                    'label' => new lang_string('tjstart', 'format_learningjourney'),
                    'element_type' => 'date_time_selector',
                    'help' => 'tjstart',
                    'help_component' => 'format_learningjourney',
                    'element_attributes' => [['optional' => true]],
                ],
                'tjend' => [
                    'label' => new lang_string('tjend', 'format_learningjourney'),
                    'element_type' => 'date_time_selector',
                    'help' => 'tjend',
                    'help_component' => 'format_learningjourney',
                    'element_attributes' => [['optional' => true]],
                ],
            ];
            $sectionformatoptions = array_merge_recursive($sectionformatoptions, $sectionformatoptionsedit);
        }
        return $sectionformatoptions;
    }

    public function course_format_options($foreditform = false) {
        static $courseformatoptions = false;
        if ($courseformatoptions === false) {
            $courseconfig = get_config('moodlecourse');
            $courseformatoptions = [
                'hiddensections' => [
                    'default' => $courseconfig->hiddensections,
                    'type' => PARAM_INT,
                ],
                'coursedisplay' => [
                    'default' => $courseconfig->coursedisplay,
                    'type' => PARAM_INT,
                ],
            ];
        }
        if ($foreditform && !isset($courseformatoptions['coursedisplay']['label'])) {
            $courseformatoptionsedit = [
                'hiddensections' => [
                    'label' => new lang_string('hiddensections'),
                    'help' => 'hiddensections',
                    'help_component' => 'moodle',
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            0 => new lang_string('hiddensectionscollapsed'),
                            1 => new lang_string('hiddensectionsinvisible'),
                        ],
                    ],
                ],
                'coursedisplay' => [
                    'label' => new lang_string('coursedisplay'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                            COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi'),
                        ],
                    ],
                    'help' => 'coursedisplay',
                    'help_component' => 'moodle',
                ],
            ];
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;
        $elements = parent::create_edit_form_elements($mform, $forsection);
        if (!$forsection && (empty($COURSE->id) || $COURSE->id == SITEID)) {
            $courseconfig = get_config('moodlecourse');
            $max = (int) $courseconfig->maxsections;
            $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
            $mform->setType('numsections', PARAM_INT);
            if (is_null($mform->getElementValue('numsections'))) {
                $mform->setDefault('numsections', $courseconfig->numsections);
            }
            array_unshift($elements, $element);
        }
        return $elements;
    }

    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array) $data;
        if ($oldcourse !== null) {
            $oldcourse = (array) $oldcourse;
            $options = $this->course_format_options();
            foreach ($options as $key => $unused) {
                if (!array_key_exists($key, $data) && array_key_exists($key, $oldcourse)) {
                    $data[$key] = $oldcourse[$key];
                }
            }
        }
        return $this->update_format_options($data);
    }

    public function delete_section($sectionornum, $forcedeleteifnotempty = false) {
        global $DB;
        $courseid = $this->get_courseid();
        if (is_object($sectionornum)) {
            $sectionid = (int) $sectionornum->id;
        } else {
            $rec = $DB->get_record('course_sections', ['course' => $courseid, 'section' => $sectionornum], 'id', IGNORE_MISSING);
            $sectionid = $rec ? (int) $rec->id : 0;
        }
        if ($sectionid > 0) {
            $context = context_course::instance($courseid);
            get_file_storage()->delete_area_files(
                $context->id,
                'format_learningjourney',
                self::FILEAREA_SECTION_IMAGE,
                $sectionid
            );
        }
        return parent::delete_section($sectionornum, $forcedeleteifnotempty);
    }

    public function can_delete_section($section) {
        return true;
    }

    public function supports_news(): bool {
        return true;
    }

    public function allow_stealth_module_visibility($cm, $section) {
        return !$section->section || $section->visible;
    }

    public function section_action($section, $action, $sr) {
        global $PAGE;
        if ($section->section && ($action === 'setmarker' || $action === 'removemarker')) {
            require_capability('moodle/course:setcurrentsection', context_course::instance($this->courseid));
            course_set_marker($this->courseid, ($action === 'setmarker') ? $section->section : 0);
            return null;
        }
        $rv = parent::section_action($section, $action, $sr);
        $renderer = $PAGE->get_renderer('format_learningjourney');
        if (!($section instanceof section_info)) {
            $modinfo = course_modinfo::instance($this->courseid);
            $section = $modinfo->get_section_info($section->section);
        }
        $elementclass = $this->get_output_classname('content\\section\\availability');
        $availability = new $elementclass($this, $section);
        $rv['section_availability'] = $renderer->render($availability);
        return $rv;
    }

    public function get_config_for_external() {
        $formatoptions = $this->get_format_options();
        $formatoptions['indentation'] = get_config('format_learningjourney', 'indentation');
        return $formatoptions;
    }

    public function get_required_jsfiles(): array {
        return [];
    }

    public function editsection_form($action, $customdata = []) {
        global $CFG;
        require_once($CFG->dirroot . '/course/format/learningjourney/classes/form/editsection_form.php');
        if (!array_key_exists('course', $customdata)) {
            $customdata['course'] = $this->get_course();
        }
        return new \format_learningjourney\form\editsection_form($action, $customdata);
    }
}

/**
 * Serve section images from this format.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param \context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return void
 */
function format_learningjourney_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    if ($context->contextlevel != CONTEXT_COURSE) {
        send_file_not_found();
    }
    if ($filearea !== format_learningjourney::FILEAREA_SECTION_IMAGE) {
        send_file_not_found();
    }

    require_login($course);

    $sectionid = (int) array_shift($args);
    if (!$DB->record_exists('course_sections', ['id' => $sectionid, 'course' => $course->id])) {
        send_file_not_found();
    }

    $sectionnum = (int) $DB->get_field('course_sections', 'section', ['id' => $sectionid], MUST_EXIST);
    $sectioninfo = get_fast_modinfo($course)->get_section_info($sectionnum);
    if (!$sectioninfo->uservisible && !has_capability('moodle/course:update', $context)) {
        send_file_not_found();
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    if (!$file = $fs->get_file($context->id, 'format_learningjourney', $filearea, $sectionid, $filepath, $filename)
            || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 60 * 60, 0, $forcedownload, $options);
}

/**
 * In-place section title editing.
 *
 * @param string $itemtype
 * @param int $itemid
 * @param mixed $newvalue
 * @return inplace_editable
 */
function format_learningjourney_inplace_editable($itemtype, $itemid, $newvalue) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/course/lib.php');
    if ($itemtype === 'sectionname' || $itemtype === 'sectionnamenl') {
        $section = $DB->get_record_sql(
            'SELECT s.* FROM {course_sections} s JOIN {course} c ON s.course = c.id WHERE s.id = ? AND c.format = ?',
            [$itemid, 'learningjourney'],
            MUST_EXIST
        );
        return course_get_format($section->course)->inplace_editable_update_section_name($section, $itemtype, $newvalue);
    }
}
