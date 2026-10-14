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
 * Contact / talk-to-us portal page (content-only / dark mode aware).
 *
 * @package    format_learningjourney
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

$course = format_learningjourney_setup_portal_page('contact', 'contentonlyfootercontact');
$returnurl = format_learningjourney_portal_url((int) $course->id, 'contact');
$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);

$user = (!isguestuser()) ? $USER : null;

// Mirror core contactsitesupport availability rules.
if (!isset($CFG->supportavailability) ||
        $CFG->supportavailability == CONTACT_SUPPORT_DISABLED ||
        ($CFG->supportavailability == CONTACT_SUPPORT_AUTHENTICATED && is_null($user))) {
    redirect($courseurl);
}

if (!empty($CFG->supportpage)) {
    redirect($CFG->supportpage);
}

$renderer = $PAGE->get_renderer('user');
$form = new \core_user\form\contactsitesupport_form($returnurl->out(false), $user);

if ($form->is_cancelled()) {
    redirect($courseurl);
} else if ($form->is_submitted() && $form->is_validated() && confirm_sesskey()) {
    $data = $form->get_data();

    $from = $user ?? core_user::get_noreply_user();
    $subject = get_string('supportemailsubject', 'admin', format_string($SITE->fullname));
    $data->notloggedinuser = (!$user);
    $message = $renderer->render_from_template('user/contact_site_support_email_body', $data);

    $sendmail = email_to_user(
        user: core_user::get_support_user(),
        from: $from,
        subject: $subject,
        messagetext: $message,
        usetrueaddress: true,
        replyto: $data->email,
        replytoname: $data->name
    );

    if (!$sendmail) {
        $supportemail = $CFG->supportemail ?? '';
        $form->set_data($data);
        $output = $renderer->render_from_template('user/contact_site_support_not_available', [
            'supportemail' => ($user && $supportemail)
                ? html_writer::link('mailto:' . $supportemail, $supportemail)
                : false,
            'supportform' => $form->render(),
        ]);
    } else {
        redirect(
            $returnurl,
            get_string('supportmessagesent', 'user'),
            3,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
} else {
    $output = $form->render();
}

echo $OUTPUT->header();
echo html_writer::start_div('lj-portal-shell lj-portal-contact');
echo html_writer::tag('h1', get_string('contentonlyfootercontact', 'format_learningjourney'), [
    'class' => 'lj-portal-heading h3',
]);
echo html_writer::div($output, 'lj-portal-contact__form');
echo html_writer::end_div();
echo $OUTPUT->footer();
