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
 * Personal area portal page (content-only / dark mode aware).
 *
 * @package    format_learningjourney
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

$course = format_learningjourney_setup_portal_page('personal', 'contentonlyfooterpersonal');

$userpicture = new user_picture($USER);
$userpicture->size = 120;
$avatarurl = $userpicture->get_url($PAGE);

$coursesurl = new moodle_url('/local/learningjourneylogin/courses.php');
$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
$logouturl = new moodle_url('/local/learningjourneylogin/logout.php', ['sesskey' => sesskey()]);
$meter = format_learningjourney_course_learning_meter($course, (int) $USER->id);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('format_learningjourney/portal_personal', [
    'heading' => get_string('contentonlyfooterpersonal', 'format_learningjourney'),
    'fullname' => fullname($USER),
    'email' => s($USER->email),
    'avatarurl' => $avatarurl->out(false),
    'courseurl' => $courseurl->out(false),
    'courselabel' => get_string('backtocourse', 'format_learningjourney'),
    'coursesurl' => $coursesurl->out(false),
    'courseslabel' => get_string('portalmycourses', 'format_learningjourney'),
    'logouturl' => $logouturl->out(false),
    'logoutlabel' => get_string('logout'),
    'meter' => $meter,
]);
echo $OUTPUT->footer();
