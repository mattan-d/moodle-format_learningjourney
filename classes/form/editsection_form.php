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
 * Section edit form with date range validation.
 *
 * @package    format_learningjourney
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_learningjourney\form;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../editsection_form.php');

/**
 * Section edit form.
 */
class editsection_form extends \editsection_form {

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        $start = $this->optional_datetime_to_timestamp($data['tjstart'] ?? null);
        $end = $this->optional_datetime_to_timestamp($data['tjend'] ?? null);
        if ($start && $end && $start > $end) {
            $errors['tjend'] = get_string('err_endbeforestart', 'format_learningjourney');
        }
        return $errors;
    }

    /**
     * @param mixed $value Raw date_time_selector value from the form.
     */
    protected function optional_datetime_to_timestamp($value): int {
        if (empty($value) || !is_array($value) || empty($value['enabled'])) {
            return 0;
        }
        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        $gregorian = $calendartype->convert_to_gregorian(
            (int) $value['year'],
            (int) $value['month'],
            (int) $value['day'],
            (int) $value['hour'],
            (int) $value['minute']
        );
        return make_timestamp(
            $gregorian['year'],
            $gregorian['month'],
            $gregorian['day'],
            $gregorian['hour'],
            $gregorian['minute'],
            0,
            99,
            true
        );
    }
}
