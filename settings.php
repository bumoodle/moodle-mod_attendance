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
 * @package mod-forum
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    // Add a configuration setting which determines whether student ID numbers should be available as an identification method.
    $settings->add(new admin_setting_configcheckbox('attendance_useidnumbers', get_string('useidnumbers', 'attendance'), get_string('configuseidnumbers', 'attendance'), '1'));

    // Add a configuration setting which allows the site administrator to specify which custom profile fields represent ID numbers. 
    $settings->add(new admin_setting_configtext('attendance_idnumberfields', get_string('idnumberfields', 'attendance'), get_string('configidnumberfields', 'attendance'), '', PARAM_TEXT));


}

