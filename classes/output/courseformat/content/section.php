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
use format_learningjourney;
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
        global $PAGE;

        $format = $this->format;
        $data = parent::export_for_template($output);
        if (!empty($data->displayonesection)) {
            $data->ljbacktocourseurl = $format->get_view_url(0)->out(false);
        }
        $data->ljscheduleincludestoday = $this->format->is_section_within_schedule($this->section);
        $data->ljcardview = $this->format->is_showing_all_sections();
        $data->ljisheader = ((int) ($this->section->tjisheader ?? 0) === 1);
        if (!empty($data->ljcardview)) {
            $showbutton = (int) ($this->section->tjshowbutton ?? 1);
            $data->ljhasgotourl = ($showbutton === 1);
            $data->ljcardclick = false;
            $data->ljcardurl = null;
            if (!$data->ljisheader && !$data->ljhasgotourl) {
                $data->ljcardclick = true;
                $data->ljcardurl = $format->get_view_url((int) $this->section->section, ['navigation' => true])->out(false);
            }
            if ($data->ljhasgotourl) {
                $data->ljgotourl = $format->get_view_url((int) $this->section->section, ['navigation' => true])->out(false);
                $label = trim((string) ($this->section->tjbuttonlabel ?? ''));
                $data->ljgotolabel = ($label !== '') ? $label : get_string('ljbuttondefault', 'format_learningjourney');
            }
            if ($data->ljisheader) {
                // Header units are visual separators: title only.
                $data->ljhasgotourl = false;
                $data->hasljdates = false;
                $data->ljcardclick = false;
                $data->ljcardurl = null;
            }
        }
        if ($this->format->is_showing_all_sections() && !format_learningjourney::section_info_is_delegated($this->section)) {
            $addsectionclass = $format->get_output_classname('content\\addsection');
            $addsection = new $addsectionclass($format);
            $data->numsections = $addsection->export_for_template($output);
            $data->insertafter = true;
        }
        $this->add_learning_journey_dates_to_export($data);
        format_learningjourney::append_section_image_to_export_data($format, $this->section, $data);

        if (!$PAGE->user_is_editing() && !$this->section->uservisible) {
            $this->strip_unavailable_section_body($data);
        }

        return $data;
    }

    /**
     * Keep title row + schedule line + image; drop activities, summary, availability and add a locked hint.
     */
    protected function strip_unavailable_section_body(stdClass $data): void {
        $data->ljcontentdisabled = true;
        $data->contentcollapsed = true;
        unset(
            $data->cmlist,
            $data->cmsummary,
            $data->cmcontrols,
            $data->summary,
            $data->availability,
            $data->onlysummary
        );
        $data->hasavailability = false;
        $data->restrictionlock = false;
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
        if (!empty($data->ljcardview)) {
            $now = time();
            if ($start > 0 && $now < $start) {
                $daysuntilstart = max(1, (int) ceil(($start - $now) / DAYSECS));
                $data->hasljdates = true;
                $data->ljrangeline = ($daysuntilstart === 1)
                    ? get_string('ljcountdownavailable1', 'format_learningjourney')
                    : get_string('ljcountdownavailable', 'format_learningjourney', $daysuntilstart);
                return;
            }
            if ($end > 0 && $now <= $end) {
                $daysuntilend = max(1, (int) ceil(($end - $now) / DAYSECS));
                $data->hasljdates = true;
                $data->ljrangeline = ($daysuntilend === 1)
                    ? get_string('ljcountdownclosing1', 'format_learningjourney')
                    : get_string('ljcountdownclosing', 'format_learningjourney', $daysuntilend);
                return;
            }
            if ($start > 0 && $now >= $start && $end <= 0) {
                $data->hasljdates = true;
                $data->ljrangeline = get_string('ljcountdownopen', 'format_learningjourney');
                return;
            }
            $startlabel = ($start > 0) ? userdate($start, $dateformat) : '';
            $endlabel = ($end > 0) ? userdate($end, $dateformat) : '';
            if ($startlabel !== '' && $endlabel !== '') {
                $data->hasljdates = true;
                $data->ljrangeline = $startlabel . ' - ' . $endlabel;
                return;
            }
            if ($startlabel !== '' || $endlabel !== '') {
                $data->hasljdates = true;
                $data->ljrangeline = ($startlabel !== '') ? $startlabel : $endlabel;
                return;
            }
        }
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
