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
 * Backend logic for live attendance capture for smartphones, and/or handheld ID scanners.
 * 
 * This work was made possible by a Innovative Instructional Technology Grant (IITG)
 * from the State University of New York. 
 *
 * @package    mod_attendance
 * @copyright  2013 Binghamton University
 * @author     Kyle J. Temkin <ktemkin@binghamton.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Mark this page as being executed by an AJAX script-- this ensures that
// error messages are returned correctly as JSON.
define('AJAX_SCRIPT', true);
 
// Require the global system configuration.
require_once('../../../config.php');

// Require the local "barcode scan" functions.
require_once('../locallib.php');

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

// Retreive the additional required parameters.
$mode     = required_param('mode', PARAM_ALPHANUM);
$uid      = required_param('user', PARAM_TEXT);
$session  = required_param('csession', PARAM_INT);

// Process the user's ID according to the provided mode.
try {
    switch($mode) {
       
        //If we've been passed an ID number, convert it to a User ID.
        case 'idnumber':
           $user = $module->get_user($module->get_user_from_id_number($uid)->id);
           break;

        //If we've been passed a User ID, use that directly.
        case 'userid':
           $user = $module->get_user($uid);
           break;

        //If we've been passed anything else, raise an exception.
        default:
            throw new moodle_exception('invalidmode', 'attendance');

    }
}
catch(dml_exception $e) {
    // Send a JSON response indicating failure.
    die(json_encode(array(
        'status'  => 'importerror',
        'uid'     => $uid,
        'message' => $e->getMessage()
    )));
}

// Get the ID numbers of the statuses to be used for 
$allstatuses = $module->get_statuses();
$present     = reset($allstatuses);
$absent      = end($allstatuses);

// Generate a remark which will be displayed to the user during check-off.
$remark =  get_string('inclass', 'attendance', userdate(time()));

// Record the user's attendance...
$module->record_single_user_attendance($user, $session, $present, $remark, $absent); 

// Send a JSON response indicating success.
echo json_encode(array(
    'status'    => 'success',
    'firstname' => $user->firstname,
    'lastname'  => $user->lastname,
    'userdate'  => userdate(time())
));

