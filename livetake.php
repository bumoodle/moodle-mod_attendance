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
 * Live attendance capture for smartphones, and/or handheld ID scanners.
 * 
 * This work was made possible by a Innovative Instructional Technology Grant (IITG)
 * from the State University of New York. 
 *
 * @package    mod_attendance
 * @copyright  2013 Binghamton University
 * @author     Kyle J. Temkin <ktemkin@binghamton.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
// Require the global system configuration.
require_once('../../config.php');

// Require the local "barcode scan" functions.
require_once('locallib.php');

//TODO: Abstract to a setting.
define('TIME_ADJUSTMENT', 60 * 15);

// Retreive the context in which attendance will be taken.
$id         = required_param('id', PARAM_INT);
$cm         = get_coursemodule_from_id('attendance', $id, 0, false, MUST_EXIST);
$course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$rawmodule  = $DB->get_record('attendance', array('id' => $cm->instance), '*', MUST_EXIST);

// Ensure that the user is logged in in the given course...
require_login($course, true, $cm);

// Create an attendance module.
$module = new attendance($rawmodule, $cm, $course, $PAGE->context, new att_take_page_params());

// If the user can't take attendance for the group, exit with an error message.
if(!$module->perm->can_take()) {
    throw new moodle_exception('cannottake', 'attendance');
}

// Set up the currently rendered page.
$PAGE->set_url($module->url_take());
$PAGE->set_title($course->shortname. ": ".$module->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(true);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attendance'));
$PAGE->navbar->add($module->name);

// Ensure that jQuery is loaded.
// TODO: Ensure chosen is loaded?
$PAGE->requires->jquery();
$PAGE->requires->jquery_plugin('chosen', 'mod_attendance');

// Ensure that the LiveTake javascript is loaded.
$PAGE->requires->js_init_call('M.mod_attendance.livetake.initialize', array($id));

// Get the object in charge of rendering the attendance module.
$output = $PAGE->get_renderer('mod_attendance');

// Generate the basic interactive form elements.
$session  = new attendance_live_session_selector($module, time() + TIME_ADJUSTMENT);
$students = new attendance_live_user_selector($module);

// Render the page itself.
echo $output->header();
echo $output->render($session);
echo $output->render($students);
echo $output->render_live_result_area();
echo $output->footer();
