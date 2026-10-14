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
 * Course content output.
 *
 * @package    format_learningjourney
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_learningjourney\output\courseformat;

use core_courseformat\output\local\content as content_base;
use renderer_base;

/**
 * Course format main content.
 */
class content extends content_base {

    /** @var bool */
    protected $hasaddsection = true;

    public function get_template_name(\renderer_base $renderer): string {
        global $PAGE;
        if ($PAGE->user_is_editing()) {
            return parent::get_template_name($renderer);
        }
        return 'format_learningjourney/local/content';
    }

    public function export_for_template(renderer_base $output) {
        global $PAGE;
        $format = $this->format;
        $data = parent::export_for_template($output);

        // Editing: use core Moodle course layout without learning journey view styling.
        if ($PAGE->user_is_editing()) {
            return $data;
        }

        $PAGE->requires->js_call_amd('format_learningjourney/mutations', 'init');
        $PAGE->requires->js_call_amd('format_learningjourney/section', 'init');
        if ($PAGE->theme->usescourseindex && $format->uses_course_index()) {
            $PAGE->requires->js_call_amd('format_learningjourney/courseindex_section_images', 'init');
        }
        $opts = $format->get_format_options();
        $layout = (int) ($opts['sectionlayout'] ?? \format_learningjourney::SECTION_LAYOUT_GRID);
        $data->ljsectionlayoutgrid = ($layout === \format_learningjourney::SECTION_LAYOUT_GRID);
        $data->ljisediting = $PAGE->user_is_editing();
        $gridcols = (int) ($opts['gridcolumns'] ?? \format_learningjourney::GRID_COLUMNS_DEFAULT);
        if ($gridcols < 2) {
            $gridcols = 2;
        } else if ($gridcols > 6) {
            $gridcols = 6;
        }
        $data->ljgridcols = $gridcols;
        $data->ljshowsection0 = ((int) ($opts['showsection0'] ?? 1) === 1);
        if (!$data->ljshowsection0) {
            unset($data->initialsection);
            if (!empty($data->sections) && is_array($data->sections)) {
                $data->sections = array_values(array_filter($data->sections, static function($section): bool {
                    $num = null;
                    if (is_object($section) && isset($section->num)) {
                        $num = (int) $section->num;
                    } else if (is_array($section) && array_key_exists('num', $section)) {
                        $num = (int) $section['num'];
                    }
                    return $num !== 0;
                }));
            }
        }

        // Always expose prev/next on single-section pages (Boost hides them when course index is on).
        if (!empty($data->hasnavigation) && empty($data->sectionnavigation)) {
            $singlesectionnum = null;
            if ($format instanceof \format_learningjourney) {
                $singlesectionnum = $format->get_display_sectionnum();
            } else if (is_callable([$format, 'get_sectionnum'])) {
                $singlesectionnum = $format->get_sectionnum();
            }
            if ($singlesectionnum !== null) {
                $sectionnavigationclass = $format->get_output_classname('content\\sectionnavigation');
                $sectionselectorclass = $format->get_output_classname('content\\sectionselector');
                $sectionnavigation = new $sectionnavigationclass($format, (int) $singlesectionnum);
                $data->sectionnavigation = $sectionnavigation->export_for_template($output);
                $sectionselector = new $sectionselectorclass($format, $sectionnavigation);
                $data->sectionselector = $sectionselector->export_for_template($output);
            }
        }

        // Full-bleed section cover under the top menu (rendered once at page level).
        $data->ljhassectioncover = false;
        if (!empty($data->singlesection) && is_object($data->singlesection)
                && !empty($data->singlesection->hassectionimage)) {
            $data->ljhassectioncover = true;
            $data->ljsectioncoverurl = $data->singlesection->sectionimageurl;
            $data->ljsectioncoveralt = $data->singlesection->sectionimagealt ?? '';
            $data->singlesection->ljcoveratsite = true;
        }

        // Course-home banner from course overview image (not on single-section pages).
        $data->ljhascoursebanner = false;
        if (empty($data->singlesection) && empty($data->hasnavigation)) {
            $course = $format->get_course();
            $courseimage = null;
            if (function_exists('course_get_courseimage')) {
                $courseimage = course_get_courseimage($course);
            } else {
                $courseinlist = new \core_course_list_element($course);
                foreach ($courseinlist->get_course_overviewfiles() as $file) {
                    if ($file->is_valid_image()) {
                        $courseimage = $file;
                        break;
                    }
                }
            }
            if ($courseimage) {
                $data->ljhascoursebanner = true;
                $data->ljcoursebannerurl = \moodle_url::make_pluginfile_url(
                    $courseimage->get_contextid(),
                    $courseimage->get_component(),
                    $courseimage->get_filearea(),
                    null,
                    $courseimage->get_filepath(),
                    $courseimage->get_filename()
                )->out(false);
                $data->ljcoursebanneralt = format_string($course->fullname, true, [
                    'context' => \context_course::instance($course->id),
                ]);
                $data->ljcoursename = $data->ljcoursebanneralt;
            }
        }

        return $data;
    }
}
