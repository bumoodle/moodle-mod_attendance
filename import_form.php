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


        // Import header...
        $mform->addElement('header', 'general', get_string('import','quiz'));

        //TODO: Static explanation text- or help?
        $mform->addElement('textarea', 'userdata', get_string('userdata', 'attforblock'), 'rows="30" cols="70"');
        $mform->addHelpButton('userdata', 'userdata', 'attforblock');
 
        $mform->addElement('date_time_selector', 'sessionstartdate', get_string('startofperiod','attforblock'));
        
        // buttons
        $this->add_action_buttons();

        $mform->addElement('hidden', 'id', $cm->id);

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

