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
 * Section state for course index / editor (adds section image fields).
 *
 * @package    format_learningjourney
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_learningjourney\output\courseformat\state;

use core_courseformat\output\local\state\section as section_base;
use format_learningjourney;
use renderer_base;
use stdClass;

/**
 * Section state export.
 */
class section extends section_base {

    public function export_for_template(renderer_base $output): stdClass {
        $data = parent::export_for_template($output);
        format_learningjourney::append_section_image_to_export_data($this->format, $this->section, $data);
        return $data;
    }
}
