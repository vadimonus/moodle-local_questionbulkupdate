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
 * Tool for questions bulk update.
 *
 * @package    qbank_bulkupdate
 * @copyright  2021 Vadim Dvorovenko <Vadimon@mail.ru>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../config.php");
require_once("$CFG->dirroot/question/editlib.php");

use core_question\local\bank\helper as core_question_local_bank_helper;
use core_question\output\qbank_action_menu;

require_login();
core_question_local_bank_helper::require_plugin_enabled('qbank_bulkupdate');

$cmid = optional_param('cmid', 0, PARAM_INT);
if ($cmid) {
    $pageparams = ['cmid' => $cmid];
    [$module, $cm] = get_module_from_cmid($cmid);
    require_login($cm->course, false, $cm);
    $PAGE->set_cm($cm);
    $context = context_module::instance($cmid);
} else {
    $courseid = required_param('courseid', PARAM_INT);
    $pageparams = ['courseid' => $courseid];
    $course = get_course($courseid);
    require_login($course);
    $PAGE->set_course($course);
    $context = context_course::instance($courseid);
}
if (!has_capability('moodle/question:editall', $context)) {
    require_capability('moodle/question:editmine', $context);
}

$PAGE->set_pagelayout('admin');
$url = new moodle_url('/question/bank/bulkupdate/bulkupdate.php', $pageparams);
$PAGE->set_url($url);
$PAGE->set_title(get_string('navandheader', 'qbank_bulkupdate'));
$PAGE->set_heading($COURSE->fullname);

$mform = new \qbank_bulkupdate\form($url, ['context' => $context]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/question/edit.php', $pageparams));
} else if ($data = $mform->get_data()) {
    require_sesskey();
    $helper = new \qbank_bulkupdate\helper();
    $helper->bulk_update($data);
    redirect(new moodle_url('/question/edit.php', $pageparams));
}

echo $OUTPUT->header();
$renderer = $PAGE->get_renderer('core_question', 'bank');
$qbankaction = new qbank_action_menu($url);
echo $renderer->render($qbankaction);

echo $OUTPUT->heading(get_string('navandheader', 'qbank_bulkupdate'));
$mform->display();
echo $OUTPUT->footer();
