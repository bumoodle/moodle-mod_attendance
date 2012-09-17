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

require_once($CFG->libdir.'/formslib.php');

class mod_attforblock_import_form extends moodleform {

    function definition() {

        global $CFG, $USER;
        $mform    =& $this->_form;

        $course        = $this->_customdata['course'];
        $cm            = $this->_customdata['cm'];
        $modcontext    = $this->_customdata['modcontext'];
        $userdata      = $this->_customdata['userdata'];
        $defaulttime   = $this->_customdata['defaulttime'];

        // Import header...
        $mform->addElement('header', 'general', get_string('import','quiz'));

        // The main CSV/Barcode area.
        $mform->addElement('textarea', 'userdata', get_string('userdata', 'attforblock'), 'rows="30" cols="70"');
        $mform->addHelpButton('userdata', 'userdata', 'attforblock');

        // If a default value for the user-data field was provided, use it.
        // This is used to restore the "persistent" user-data field.
        if(!empty($userdata)) {
            $mform->setDefault('userdata', $userdata);
        }

        // The default time selector.
        // TODO: Select the most recent session, by default?
        $mform->addElement('date_time_selector', 'defaulttime', get_string('defaulttime','attforblock'));
        $mform->addHelpButton('defaulttime', 'defaulttime', 'attforblock');

        // If a default value for the user-data field was provided, use it.
        // This is used to restore the "persistent" user-data field.
        if(!empty($defaulttime)) {
            $mform->setDefault('defaulttime', $defaulttime);
        }

        // Add a status selector for students present and missing.
        $statuses = $this->get_statuses();
        $acronyms = array_keys($statuses);
        $mform->addElement('select', 'defaultstatus_included', get_string('defaultstatus_included', 'attforblock'), $statuses);
        $mform->addHelpButton('defaultstatus_included', 'defaultstatus_included', 'attforblock');
        $mform->setDefault('defaultstatus_omitted', $acronyms[0]);

        // Add a "do not change" status, for use in the "omitted students will be" field.
        $statuses['-'] = get_string('nochange', 'attforblock');
        $mform->addElement('select', 'defaultstatus_omitted', get_string('defaultstatus_omitted', 'attforblock'), $statuses);
        $mform->addHelpButton('defaultstatus_omitted', 'defaultstatus_omitted', 'attforblock');
        $mform->setDefault('defaultstatus_omitted', end($acronyms));
        
        // buttons
        $this->add_action_buttons();
        $mform->addElement('hidden', 'id', $cm->id);

    }

    /**
     * Overrides the Moodleform's user field data.
     * TODO: Find a more paradigmatic way to do this.
     * 
     * @param string $value The value with which to override the userdata field.
     * @return void
     */
    public function set_user_data($value) {

        // Get the index of the MoodleForm element which corresponds to the user-data field.
        $dataindex = $this->_form->_elementIndex['userdata'];

        // And override that element's value with the value provided.
        $this->_form->_elements[$dataindex]->_value = $value;    
    }

    /**
     * @return array An associative array mapping status acronyms to strings describing those statuses.
     */
    private function get_statuses() {

        // Create an empty array, which will house each of the statuses.
        $statuses = array();

        // For each of the statuses which can be assigned to a student...
        foreach($this->_customdata['statuses'] as $data) {
            $statuses[$data->acronym] = get_string('statusformat', 'attforblock', $data);
        }

        // Returns the newly created array of statuses.
        return $statuses;

    }

//    function validation($data, $files) {
//        $errors = parent::validation($data, $files);
//        if (($data['timeend']!=0) && ($data['timestart']!=0)
//            && $data['timeend'] <= $data['timestart']) {
//                $errors['timeend'] = get_string('timestartenderror', 'forum');
//            }
//        return $errors;
//    }

}

