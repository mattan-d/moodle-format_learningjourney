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
 * Section output.
 *
 * @package    format_learningjourney
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_learningjourney\output\courseformat\content;

use core_courseformat\output\local\content\section as section_base;
use renderer_base;
use stdClass;

/**
 * Single section on the course page.
 */
class section extends section_base {

    public function get_template_name(\renderer_base $renderer): string {
        return 'format_learningjourney/local/content/section';
    }

    public function export_for_template(renderer_base $output): stdClass {
        $format = $this->format;
        $data = parent::export_for_template($output);
        if (!$this->format->get_sectionnum() && !$this->section->is_delegated()) {
            $addsectionclass = $format->get_output_classname('content\\addsection');
            $addsection = new $addsectionclass($format);
            $data->numsections = $addsection->export_for_template($output);
            $data->insertafter = true;
        }
        $this->add_learning_journey_dates_to_export($data);
        return $data;
    }

    /**
     * Expose section schedule on the course page (start/end from format options).
     */
    protected function add_learning_journey_dates_to_export(stdClass $data): void {
        $section = $this->section;
        if ((int) $section->section === 0) {
            return;
        }
        $start = (int) ($section->tjstart ?? 0);
        $end = (int) ($section->tjend ?? 0);
        if ($start <= 0 && $end <= 0) {
            return;
        }
        $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        $parts = [];
        if ($start > 0) {
            $parts[] = get_string('ljdisplayfrom', 'format_learningjourney', userdate($start, $dateformat));
        }
        if ($end > 0) {
            $parts[] = get_string('ljdisplayuntil', 'format_learningjourney', userdate($end, $dateformat));
        }
        if (!$parts) {
            return;
        }
        $data->hasljdates = true;
        $data->ljrangeline = implode(get_string('ljdatesseparator', 'format_learningjourney'), $parts);
    }
}
