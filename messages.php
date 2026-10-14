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
 * Messages portal page (content-only / dark mode aware).
 *
 * @package    format_learningjourney
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

$course = format_learningjourney_setup_portal_page('messages', 'contentonlyfootermessages');

if (empty($CFG->messaging)) {
    throw new moodle_exception('disabled', 'message');
}

$view = optional_param('view', null, PARAM_ALPHANUM);
// Course id occupies ?id=; messaging peers use ?user2= / ?convid=.
$userid = optional_param('user2', 0, PARAM_INT);
$conversationid = optional_param('convid', null, PARAM_INT);

if (!core_user::is_real_user($userid)) {
    $userid = null;
}

if ($userid) {
    $conversationid = \core_message\api::get_conversation_between_users([$USER->id, $userid]);
} else if ($conversationid) {
    if (!\core_message\api::is_user_in_conversation($USER->id, $conversationid)) {
        $conversationid = null;
    }
}

if ($userid && !\core_message\api::can_send_message($userid, $USER->id)) {
    throw new moodle_exception('Can not contact user');
}

echo $OUTPUT->header();
echo html_writer::start_div('lj-portal-shell lj-portal-messages');
echo html_writer::tag('h1', get_string('contentonlyfootermessages', 'format_learningjourney'), [
    'class' => 'lj-portal-heading h3',
]);
echo \core_message\helper::render_messaging_widget(false, $userid, $conversationid, $view);
echo html_writer::end_div();
echo $OUTPUT->footer();
