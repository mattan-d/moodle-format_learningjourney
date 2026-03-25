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
 * Language strings.
 *
 * @package    format_learningjourney
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Learning journey';
$string['section0name'] = 'General';
$string['newsection'] = 'New section';
$string['sectionoutline'] = 'Section outline';
$string['showfromothers'] = 'Show from others';
$string['sectionlayout'] = 'Section layout';
$string['sectionlayout_list'] = 'Single column (full width)';
$string['sectionlayout_grid'] = 'Grid (up to three sections per row on large screens)';
$string['sectionlayout_help'] = 'Choose how sections are arranged on the course main page. In editing mode the page always uses a single column for easier management. On small screens the grid may show one or two columns before reaching three.';
$string['gridcolumns'] = 'Cards per row (grid)';
$string['gridcolumns_help'] = 'How many cards to show per row in grid view on large screens. On small screens fewer columns will be used automatically.';
$string['gridcolumns_2'] = '2';
$string['gridcolumns_3'] = '3';
$string['gridcolumns_4'] = '4';
$string['gridcolumns_5'] = '5';
$string['gridcolumns_6'] = '6';
$string['showsection0'] = 'Show introduction section (section 0)';
$string['showsection0_help'] = 'If enabled, section 0 is shown at the top of the course page.';
$string['tjbuttonlabel'] = 'Card button text';
$string['tjbuttonlabel_help'] = 'Optional. Shown on the “open section” button in card view. If empty, defaults to "Click here".';
$string['tjshowbutton'] = 'Show card button';
$string['tjshowbutton_help'] = 'If enabled, shows a button to enter the section in card view.';
$string['tjisheader'] = 'Header unit';
$string['tjisheader_help'] = 'If enabled, this section is shown as a full-width, subtle header (title only) in card view.';
$string['tjstart'] = 'Section start';
$string['tjstart_help'] = 'Optional. Learners only see this section from the calendar day of this date (in their timezone) onward. Leave disabled for no start limit.';
$string['tjend'] = 'Section end';
$string['tjend_help'] = 'Optional. Learners only see this section up to and including the calendar day of this date (in their timezone). Leave disabled for no end limit.';
$string['err_endbeforestart'] = 'End date must be the same as or after the start date.';
$string['ljdisplayfrom'] = 'Available from {$a}';
$string['ljdisplayuntil'] = 'Available until {$a}';
$string['ljdatesseparator'] = ' · ';
$string['ljcountdownavailable'] = 'In {$a} days';
$string['ljcountdownavailable1'] = 'In 1 day';
$string['ljcountdownclosing'] = 'Closes in {$a} days';
$string['ljcountdownclosing1'] = 'Closes in 1 day';
$string['ljcountdownopen'] = 'Open now';
$string['ljopensection'] = 'Open section';
$string['ljbuttondefault'] = 'Click here';
$string['sectionimage'] = 'Section image';
$string['sectionimage_help'] = 'Optional image shown at the top of this section on the course page. One image file only.';
$string['sectioncontentlocked'] = 'This section’s activities are not available in the current period. The title, schedule and image are shown as a preview.';
$string['privacy:metadata'] = 'The Learning journey course format plugin does not store personal data. Optional section images are stored as files in the course context.';
