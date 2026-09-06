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

    /** @var int Course page: one section per row (full width). */
    public const SECTION_LAYOUT_LIST = 0;

    /** @var int Course page: responsive grid up to three sections per row. */
    public const SECTION_LAYOUT_GRID = 1;

    /** @var int Default number of columns for grid view. */
    public const GRID_COLUMNS_DEFAULT = 3;

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
     * Add hassectionimage / sectionimageurl / sectionimagealt to a template or state export object.
     */
    public static function append_section_image_to_export_data(course_format_base $format, section_info $section, stdClass $data): void {
        $context = context_course::instance($format->get_course()->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'format_learningjourney',
            self::FILEAREA_SECTION_IMAGE,
            $section->id,
            'itemid, filepath, filename',
            false
        );
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $data->hassectionimage = true;
            $data->sectionimageurl = moodle_url::make_pluginfile_url(
                $context->id,
                'format_learningjourney',
                self::FILEAREA_SECTION_IMAGE,
                $section->id,
                '/',
                $file->get_filename()
            )->out(false);
            $data->sectionimagealt = s($format->get_section_name($section));
            return;
        }
    }

    /**
     * True when the section is delegated to another component ({@see section_info::is_delegated()}).
     *
     * Older Moodle builds omit that API; treat as not delegated.
     */
    public static function section_info_is_delegated(section_info $section): bool {
        return method_exists($section, 'is_delegated') && $section->is_delegated();
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
     * Keep schedule-locked sections in the course outline for students (title + image teaser).
     *
     * Core {@see course_format_base::is_section_visible()} hides sections when !uservisible and
     * {@see $section->availableinfo} is empty — our hook clears availableinfo, so we must list
     * date-locked sections explicitly before falling back to core rules.
     */
    public function is_section_visible(section_info $section): bool {
        if ($this->section_is_orphan($section)) {
            return parent::is_section_visible($section);
        }
        global $USER;
        if (
            (int) $section->section !== 0
            && $section->visible
            && !$this->user_bypasses_section_schedule((int) ($USER->id ?? 0))
            && !$this->is_section_within_schedule($section)
        ) {
            return true;
        }
        return parent::is_section_visible($section);
    }

    /**
     * Whether the section is orphaned (beyond last section, or delegated plugin missing).
     *
     * {@see section_info::is_orphan()} exists from newer Moodle; older sites need the same rules.
     */
    protected function section_is_orphan(section_info $section): bool {
        if (method_exists($section, 'is_orphan')) {
            return $section->is_orphan();
        }
        $courseformat = course_get_format($section->modinfo->get_course());
        if ((int) $section->section > $courseformat->get_last_section_number()) {
            return true;
        }
        if (self::section_info_is_delegated($section)) {
            if (method_exists($section, 'get_component_instance') && !$section->get_component_instance()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Teachers and managers bypass date restrictions.
     */
    protected function user_bypasses_section_schedule(int $userid): bool {
        if ($userid <= 0) {
            return false;
        }
        $context = context_course::instance($this->get_courseid());
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

    /**
     * Show one section by number (URL ?section=). Uses core APIs available on the running Moodle version.
     *
     * @since Moodle 4.4 core provides {@see parent::set_sectionnum()}; older versions use {@see parent::set_sectionid()}
     * or the deprecated {@see parent::set_section_number()}.
     */
    public function set_display_section(?int $sectionnum): void {
        if ($sectionnum === null) {
            return;
        }
        if (is_callable([$this, 'set_sectionnum'])) {
            $this->set_sectionnum($sectionnum);
            return;
        }
        $course = $this->get_course();
        if (!$course) {
            return;
        }
        $modinfo = get_fast_modinfo($course);
        $sectioninfo = $modinfo->get_section_info((int) $sectionnum, IGNORE_MISSING);
        if ($sectioninfo !== null && is_callable([$this, 'set_sectionid'])) {
            $this->set_sectionid((int) $sectioninfo->id);
            return;
        }
        if (is_callable([$this, 'set_section_number'])) {
            $this->set_section_number((int) $sectionnum);
        }
    }

    /**
     * Current single-section number, or null when showing all sections.
     *
     * Moodle 4.4+ has {@see course_format_base::get_sectionnum()}; older versions use
     * get_sectionid() / get_section_number() instead.
     */
    public function get_display_sectionnum(): ?int {
        $parent = get_parent_class($this);
        if ($parent && method_exists($parent, 'get_sectionnum')) {
            return parent::get_sectionnum();
        }
        if ($parent && method_exists($parent, 'get_sectionid')) {
            $sectionid = parent::get_sectionid();
            if ($sectionid === null) {
                return null;
            }
            $section = $this->get_modinfo()->get_section_info_by_id((int) $sectionid, IGNORE_MISSING);
            return $section ? (int) $section->section : null;
        }
        if ($parent && method_exists($parent, 'get_section_number')) {
            $num = (int) parent::get_section_number();
            return $num === 0 ? null : $num;
        }
        return null;
    }

    /**
     * True when the main course page lists all sections (not a single-section view).
     */
    public function is_showing_all_sections(): bool {
        return $this->get_display_sectionnum() === null;
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
        global $CFG;
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
            // Dedicated section page exists in newer Moodle only; older installs 404 on /course/section.php.
            if (file_exists($CFG->dirroot . '/course/section.php')) {
                return new moodle_url('/course/section.php', ['id' => $sectioninfo->id]);
            }
            return new moodle_url('/course/view.php', [
                'id' => $course->id,
                'section' => $sectioninfo->section,
            ]);
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
        $sectionformatoptions = [
            'tjstart' => [
                'default' => 0,
                'type' => PARAM_INT,
            ],
            'tjend' => [
                'default' => 0,
                'type' => PARAM_INT,
            ],
            'tjbuttonlabel' => [
                'default' => '',
                'type' => PARAM_TEXT,
            ],
            'tjbuttoncmid' => [
                'default' => 0,
                'type' => PARAM_INT,
            ],
            'tjshowbutton' => [
                'default' => 1,
                'type' => PARAM_INT,
            ],
            'tjisheader' => [
                'default' => 0,
                'type' => PARAM_INT,
            ],
        ];
        if ($foreditform) {
            $activitychoices = [
                0 => get_string('tjbuttonlinknone', 'format_learningjourney'),
            ];
            if ($this->get_courseid()) {
                $modinfo = get_fast_modinfo($this->get_course());
                foreach ($modinfo->cms as $cm) {
                    if (!empty($cm->deletioninprogress)) {
                        continue;
                    }
                    if (!$cm->url) {
                        continue;
                    }
                    $activitychoices[$cm->id] = shorten_text($cm->get_formatted_name(), 100);
                }
            }
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
                'tjbuttonlabel' => [
                    'label' => new lang_string('tjbuttonlabel', 'format_learningjourney'),
                    'element_type' => 'text',
                    'help' => 'tjbuttonlabel',
                    'help_component' => 'format_learningjourney',
                ],
                'tjbuttoncmid' => [
                    'label' => new lang_string('tjbuttonlink', 'format_learningjourney'),
                    'element_type' => 'select',
                    'element_attributes' => [$activitychoices],
                    'help' => 'tjbuttonlink',
                    'help_component' => 'format_learningjourney',
                ],
                'tjshowbutton' => [
                    'label' => new lang_string('tjshowbutton', 'format_learningjourney'),
                    'element_type' => 'checkbox',
                    'help' => 'tjshowbutton',
                    'help_component' => 'format_learningjourney',
                ],
                'tjisheader' => [
                    'label' => new lang_string('tjisheader', 'format_learningjourney'),
                    'element_type' => 'checkbox',
                    'help' => 'tjisheader',
                    'help_component' => 'format_learningjourney',
                ],
            ];
            $sectionformatoptions = array_merge_recursive($sectionformatoptions, $sectionformatoptionsedit);
        }
        return $sectionformatoptions;
    }

    public function edit_form_validation($data, $files, $errors) {
        $validationerrors = parent::edit_form_validation($data, $files, $errors);
        $cmid = (int) ($data['tjbuttoncmid'] ?? 0);
        if ($cmid > 0 && !empty($data['course'])) {
            $modinfo = get_fast_modinfo((int) $data['course']);
            if (!isset($modinfo->cms[$cmid])) {
                $validationerrors['tjbuttoncmid'] = get_string('invalidactivity', 'format_learningjourney');
            }
        }
        return $validationerrors;
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
                'sectionlayout' => [
                    'default' => self::SECTION_LAYOUT_GRID,
                    'type' => PARAM_INT,
                ],
                'gridcolumns' => [
                    'default' => self::GRID_COLUMNS_DEFAULT,
                    'type' => PARAM_INT,
                ],
                'showsection0' => [
                    'default' => 1,
                    'type' => PARAM_INT,
                ],
                'contentonly' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
                'darkmode' => [
                    'default' => 0,
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
                'sectionlayout' => [
                    'label' => new lang_string('sectionlayout', 'format_learningjourney'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            self::SECTION_LAYOUT_LIST => new lang_string('sectionlayout_list', 'format_learningjourney'),
                            self::SECTION_LAYOUT_GRID => new lang_string('sectionlayout_grid', 'format_learningjourney'),
                        ],
                    ],
                    'help' => 'sectionlayout',
                    'help_component' => 'format_learningjourney',
                ],
                'gridcolumns' => [
                    'label' => new lang_string('gridcolumns', 'format_learningjourney'),
                    'element_type' => 'select',
                    'element_attributes' => [
                        [
                            2 => new lang_string('gridcolumns_2', 'format_learningjourney'),
                            3 => new lang_string('gridcolumns_3', 'format_learningjourney'),
                            4 => new lang_string('gridcolumns_4', 'format_learningjourney'),
                            5 => new lang_string('gridcolumns_5', 'format_learningjourney'),
                            6 => new lang_string('gridcolumns_6', 'format_learningjourney'),
                        ],
                    ],
                    'help' => 'gridcolumns',
                    'help_component' => 'format_learningjourney',
                ],
                'showsection0' => [
                    'label' => new lang_string('showsection0', 'format_learningjourney'),
                    'element_type' => 'checkbox',
                    'help' => 'showsection0',
                    'help_component' => 'format_learningjourney',
                ],
                'contentonly' => [
                    'label' => new lang_string('contentonly', 'format_learningjourney'),
                    'element_type' => 'checkbox',
                    'help' => 'contentonly',
                    'help_component' => 'format_learningjourney',
                ],
                'darkmode' => [
                    'label' => new lang_string('darkmode', 'format_learningjourney'),
                    'element_type' => 'checkbox',
                    'help' => 'darkmode',
                    'help_component' => 'format_learningjourney',
                ],
            ];
            $courseformatoptions = array_merge_recursive($courseformatoptions, $courseformatoptionsedit);
        }
        return $courseformatoptions;
    }

    /**
     * Whether the course page should render without Moodle chrome (header/footer/nav).
     */
    public function is_content_only_view(): bool {
        $opts = $this->get_format_options();
        return !empty($opts['contentonly']);
    }

    /**
     * Whether dark mode is enabled for content-only course display.
     */
    public function is_dark_mode(): bool {
        $opts = $this->get_format_options();
        return !empty($opts['contentonly']) && !empty($opts['darkmode']);
    }

    public function create_edit_form_elements(&$mform, $forsection = false) {
        global $COURSE;
        $elements = parent::create_edit_form_elements($mform, $forsection);
        if (!$forsection) {
            if ($mform->elementExists('darkmode') && $mform->elementExists('contentonly')) {
                $mform->disabledIf('darkmode', 'contentonly', 'notchecked');
            }
            if ((empty($COURSE->id) || $COURSE->id == SITEID)) {
                $courseconfig = get_config('moodlecourse');
                $max = (int) $courseconfig->maxsections;
                $element = $mform->addElement('select', 'numsections', get_string('numberweeks'), range(0, $max ?: 52));
                $mform->setType('numsections', PARAM_INT);
                if (is_null($mform->getElementValue('numsections'))) {
                    $mform->setDefault('numsections', $courseconfig->numsections);
                }
                array_unshift($elements, $element);
            }
        }
        return $elements;
    }

    public function update_course_format_options($data, $oldcourse = null) {
        $data = (array) $data;
        // Checkbox values may be omitted from form submission when unchecked.
        // For those options, absence means "0" rather than "keep old value".
        if (!array_key_exists('showsection0', $data)) {
            $data['showsection0'] = 0;
        }
        if (!array_key_exists('contentonly', $data)) {
            $data['contentonly'] = 0;
        }
        if (!array_key_exists('darkmode', $data)) {
            $data['darkmode'] = 0;
        }
        // Dark mode only applies together with content-only.
        if (empty($data['contentonly'])) {
            $data['darkmode'] = 0;
        }
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
        if ($action === 'add') {
            return parent::section_action($section, $action, $sr);
        }
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
 * Whether the current page is a learner-facing page of a learningjourney course.
 *
 * Covers the course home, single-section pages, and activity (incourse) pages.
 */
function format_learningjourney_is_learner_facing_page(): bool {
    global $PAGE;

    $pagetype = (string) ($PAGE->pagetype ?? '');
    if ($pagetype === 'course-view-learningjourney'
            || $pagetype === 'course-view-section-learningjourney') {
        return true;
    }

    $course = $PAGE->course ?? null;
    if (!$course || empty($course->id) || (int) $course->id === SITEID) {
        return false;
    }
    if (($course->format ?? '') !== 'learningjourney') {
        return false;
    }

    // Activity pages and other in-course module layouts.
    return ($PAGE->pagelayout ?? '') === 'incourse';
}

/**
 * Whether content-only (no Moodle header/footer) should apply on this request.
 */
function format_learningjourney_should_apply_content_only(): bool {
    global $PAGE;

    if (!format_learningjourney_is_learner_facing_page()) {
        return false;
    }
    if ($PAGE->user_is_editing()) {
        return false;
    }

    $format = course_get_format($PAGE->course);
    return ($format instanceof format_learningjourney) && $format->is_content_only_view();
}

/**
 * Compact header for content-only pages: hamburger course menu + optional edit control.
 */
function format_learningjourney_content_only_editbar_html(): string {
    global $PAGE, $OUTPUT, $SITE;

    if (!format_learningjourney_should_apply_content_only()) {
        return '';
    }

    $PAGE->requires->js_call_amd('format_learningjourney/contentonly', 'init');

    $course = $PAGE->course;
    $format = course_get_format($course);
    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);

    $menusections = [];
    $modinfo = get_fast_modinfo($course);
    $showsection0 = ((int) ($format->get_format_options()['showsection0'] ?? 1) === 1);
    foreach ($modinfo->get_section_info_all() as $sectioninfo) {
        if ((int) $sectioninfo->section === 0 && !$showsection0) {
            continue;
        }
        if (!$format->is_section_visible($sectioninfo)) {
            continue;
        }
        $url = $format->get_view_url($sectioninfo, ['navigation' => true]);
        $menusections[] = [
            'name' => $format->get_section_name($sectioninfo),
            'url' => $url->out(false),
            'active' => $PAGE->url->compare($url, URL_MATCH_EXACT),
        ];
    }

    $menuoptions = [];
    try {
        $secondary = $PAGE->secondarynav;
        if ($secondary) {
            foreach ($secondary->children as $node) {
                if (!$node->display || !$node->has_action()) {
                    continue;
                }
                $action = $node->action;
                if (!($action instanceof moodle_url)) {
                    continue;
                }
                // Skip pure course-home duplicates; sections cover navigation.
                if ($action->compare($courseurl, URL_MATCH_BASE)) {
                    $params = $action->params();
                    unset($params['id']);
                    if ($params === []) {
                        continue;
                    }
                }
                $menuoptions[] = [
                    'name' => is_string($node->text) ? $node->text : $node->get_content(),
                    'url' => $action->out(false),
                    'active' => !empty($node->isactive),
                ];
            }
        }
    } catch (Throwable $e) {
        // Secondary nav may not initialise on every pagetype; sections menu is enough.
        debugging($e->getMessage(), DEBUG_DEVELOPER);
    }

    $editurl = null;
    if ($PAGE->user_allowed_editing()) {
        $editurl = (new moodle_url($PAGE->url, ['edit' => 1, 'sesskey' => sesskey()]))->out(false);
    }

    $logourl = $OUTPUT->get_compact_logo_url(null, 100);
    if (!$logourl) {
        $logourl = $OUTPUT->get_logo_url(null, 100);
    }
    $sitename = format_string($SITE->fullname, true, [
        'context' => context_course::instance(SITEID),
    ]);

    return $OUTPUT->render_from_template('format_learningjourney/contentonly_header', [
        'coursename' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
        'courseurl' => $courseurl->out(false),
        'homeurl' => (new moodle_url('/'))->out(false),
        'sitename' => $sitename,
        'haslogo' => !empty($logourl),
        'logourl' => $logourl ? $logourl->out(false) : null,
        'menulabel' => get_string('contentonlymenu', 'format_learningjourney'),
        'coursemenulabel' => get_string('contentonlycoursemenu', 'format_learningjourney'),
        'sectionslabel' => get_string('sections'),
        'optionslabel' => get_string('contentonlyoptions', 'format_learningjourney'),
        'closelabel' => get_string('closebuttontitle'),
        'hassections' => $menusections !== [],
        'sections' => $menusections,
        'hasoptions' => $menuoptions !== [],
        'options' => $menuoptions,
        'editurl' => $editurl,
        'editlabel' => get_string('turneditingon'),
        'hamburgericon' => $OUTPUT->pix_icon('i/menubars', get_string('contentonlymenu', 'format_learningjourney')),
    ]);
}

/**
 * Register stylesheet before HTTP headers. Course format rendering runs after {@see $OUTPUT->header()}.
 */
function format_learningjourney_before_http_headers(): void {
    global $PAGE;

    if (!format_learningjourney_is_learner_facing_page()) {
        return;
    }

    $pagetype = (string) ($PAGE->pagetype ?? '');
    $isformatview = ($pagetype === 'course-view-learningjourney'
        || $pagetype === 'course-view-section-learningjourney');

    // Format CSS applies to learner view only; editing uses standard Moodle chrome.
    if (!$PAGE->user_is_editing() && ($isformatview || format_learningjourney_should_apply_content_only())) {
        $PAGE->requires->css(new moodle_url('/course/format/learningjourney/styles.css'));
    }

    // Content-only: drop Moodle header/footer/nav via the embedded layout.
    // Keep the full course layout while editing so teachers can manage the course.
    if (format_learningjourney_should_apply_content_only()) {
        $PAGE->set_pagelayout('embedded');
        $PAGE->add_body_class('lj-content-only');
        $format = course_get_format($PAGE->course);
        if ($format instanceof format_learningjourney && $format->is_dark_mode()) {
            $PAGE->add_body_class('lj-dark-mode');
        }
    }
}

/**
 * Inject the content-only edit bar at the top of the body (course, section, activities).
 *
 * @return string
 */
function format_learningjourney_before_standard_top_of_body_html(): string {
    return format_learningjourney_content_only_editbar_html();
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
        $canviewhidden = has_capability('moodle/course:viewhiddensections', $context);
        if (!$sectioninfo->visible && !$canviewhidden) {
            send_file_not_found();
        }
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'format_learningjourney', $filearea, $sectionid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
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
