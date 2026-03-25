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
        return 'format_learningjourney/local/content';
    }

    public function export_for_template(renderer_base $output) {
        global $PAGE;
        $format = $this->format;
        $PAGE->requires->js_call_amd('format_learningjourney/mutations', 'init');
        $PAGE->requires->js_call_amd('format_learningjourney/section', 'init');
        if ($PAGE->theme->usescourseindex && $format->uses_course_index()) {
            $PAGE->requires->js_call_amd('format_learningjourney/courseindex_section_images', 'init');
        }
        $data = parent::export_for_template($output);
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

        return $data;
    }
}
