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
 * Export attendance sessions
 *
 * @package    mod
 * @subpackage attforblock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


require_once(dirname(__FILE__).'/../../config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/import_form.php');
require_once(dirname(__FILE__).'/renderables.php');
require_once(dirname(__FILE__).'/renderhelpers.php');

$id             = required_param('id', PARAM_INT);

$cm             = get_coursemodule_from_id('attforblock', $id, 0, false, MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$att            = $DB->get_record('attforblock', array('id' => $cm->instance), '*', MUST_EXIST);

// Ensure that the user is logged in, and create the Moodle globals (e.g. $PAGE).
require_login($course, true, $cm);

// Create a new Attendance Module object.
$att = new attforblock($att, $cm, $course, $PAGE->context);

//FIXME require import capability
$att->perm->require_export_capability();

// Set up the page's information and nav controls.
$PAGE->set_url($att->url_import());
$PAGE->set_title($course->shortname. ": ".$att->name);
$PAGE->set_heading($course->fullname);
$PAGE->set_cacheable(false);
$PAGE->set_button($OUTPUT->update_module_button($cm->id, 'attforblock'));
$PAGE->navbar->add(get_string('import', 'quiz'));

// Specify the data which will be used to render the data input form.
$params = array(
    'course' => $course, 
    'cm' => $cm, 
    'modcontext' => $PAGE->context,
    'statuses' => $att->get_statuses(),
    'userdata' => $att->get_persistent_import_text(),
    'defaulttime' => $att->most_recent_session_start()
);

// Create a new attendance import form.
$mform = new mod_attforblock_import_form($att->url_import(), $params);

// And create a new object which will track the results of any operations performed.
$result = new attforblock_import_result();

// If we've recieved the result of a form submission, proces the import.
if ($mform->is_submitted()) {

    // Get the submitted form data.
    $data = $mform->get_data();

    // Determine the default status for any submission without a status specified
    $defaultstatus = $att->status_from_string($data->defaultstatus_included); 

    // Determine the default status for any omitted users, if "no change" is not selected.
    if($data->defaultstatus_omitted !== '-') {
        $defaultomitted = $att->status_from_string($data->defaultstatus_omitted);
    } else {

        //If no change /is/ selected, do not fill empty attendance records.
        $defaultomitted = false;
    }

    // Determine the default time for any submission without a date specified.
    $defaulttime = $data->defaulttime;

    // Get all of the submitted lines.
    $lines = explode("\n", $data->userdata);

    // Create an array to keep track of the class sessions that have been modified.
    $sessions = array();

    // Start a string which will represent the new value
    // of the form field.
    $data->userdata = '';

    // Iterate over each of the submitted lines.
    foreach($lines as $line) {

        // If a line has no content, then ignore it.
        if(empty($line)) {

            // If we've already added some text to the new value, add a new-line for each empty line.
            // This allows us to maintain some semblance of the input's original form.
            if(!empty($data->userdata)) {
                $data->userdata .= "\n";
            }

            // Continue, as not to process empty lines.
            continue;
        }

        try 
        {
            // Process the given line...
            $session = $att->import_attendance_record($line, $data->defaulttime, $defaultstatus, false);

            // .. and add the session to the list of sessions that require update.
            $sessions[$session] = $session;

            // If no import error was thrown, count this import as a success.
            $result->success_count++;
        }
        // If an import exception occurs...
        catch(attforblock_import_exception $e) {

            // Add the error message to the array of errors...
            $result->log_error($e);

            // And keep the line in the "userdata" field.
            $data->userdata .= $line."\n";
        }
    }

    
    // Perform the post-update maintainence tasks on each of the affected sessions.
    foreach($sessions as $session) {
        // If a default status was provided for empty values, apply it to each of the relevant sessions.
        if($defaultomitted) {
            $att->fill_empty_attendance_records($session, $defaultomitted);
        }

        // And update the affected session's last attendance time.
        $att->update_session_attendance_time($session);
    }

    // Replace the user-data with the newly constructed user-data (which has the successfully processed
    // data removed.)
    $mform->set_user_data($data->userdata);

    // And save the value of the userdata field, so it will persist for future loads.
    $att->set_persistent_import_text($data->userdata);

}

// Get the object which is used to render Attendance Block objects.
$output = $PAGE->get_renderer('mod_attforblock');

// Generate the HTML code for the tabs at the top of the Attendance block pages.
// Note that we're specifying the active tab as TAB_IMPORT.
$tabs = new attforblock_tabs($att, attforblock_tabs::TAB_IMPORT);

// Output the page's header; and the heading.
echo $output->header();
echo $output->heading(get_string('attendanceforthecourse','attforblock').' :: ' .$course->fullname);

// Render the top tab bar.
echo $output->render($tabs);

// Render the import result, if applicable.
if($result->has_renderable_data()) {
    echo $output->render($result);
}

// Display the import form...
$mform->display();

// And display the page's footer.
echo $output->footer();


