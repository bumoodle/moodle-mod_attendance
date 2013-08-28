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
 * local functions and constants for module attendance
 *
 * @package   mod_attendance
 * @copyright  2011 Artem Andreev <andreev.artem@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/gradelib.php');
require_once(dirname(__FILE__).'/renderhelpers.php');

define('ATT_VIEW_DAYS', 1);
define('ATT_VIEW_WEEKS', 2);
define('ATT_VIEW_MONTHS', 3);
define('ATT_VIEW_ALLPAST', 4);
define('ATT_VIEW_ALL', 5);

define('ATT_SORT_LASTNAME', 1);
define('ATT_SORT_FIRSTNAME', 2);

/**
 * Generic class representing an exception which occurs during the import
 * of a specific attendance record.
 * 
 * @uses moodle_exception
 * @package mod
 * @subpackage attendance
 * @copyright 2011, 2012 Binghamton University
 * @author Kyle Temkin <ktemkin@binghamton.edu> 
 * @license GNU Public License, {@link http://www.gnu.org/copyleft/gpl.html}
 */
class attendance_import_exception extends moodle_exception {}

class attendance_permissions {
    private $canview;
    private $canviewreports;
    private $cantake;
    private $canchange;
    private $canmanage;
    private $canchangepreferences;
    private $canexport;
    private $canbelisted;
    private $canaccessallgroups;

    private $cm;
    private $context;

    public function __construct($cm, $context) {
        $this->cm = $cm;
        $this->context = $context;
    }

    public function can_view() {
        if (is_null($this->canview)) {
            $this->canview = has_capability('mod/attendance:view', $this->context);
        }

        return $this->canview;
    }

    public function require_view_capability() {
        require_capability('mod/attendance:view', $this->context);
    }

    public function can_view_reports() {
        if (is_null($this->canviewreports)) {
            $this->canviewreports = has_capability('mod/attendance:viewreports', $this->context);
        }

        return $this->canviewreports;
    }

    public function require_view_reports_capability() {
        require_capability('mod/attendance:viewreports', $this->context);
    }

    public function can_take() {
        if (is_null($this->cantake)) {
            $this->cantake = has_capability('mod/attendance:takeattendances', $this->context);
        }

        return $this->cantake;
    }

    public function can_take_session($groupid) {
        if (!$this->can_take()) {
            return false;
        }

        if ($groupid == attendance::SESSION_COMMON
            || $this->can_access_all_groups()
            || array_key_exists($groupid, groups_get_activity_allowed_groups($this->cm))) {
            return true;
        }

        return false;
    }

    public function can_change() {
        if (is_null($this->canchange)) {
            $this->canchange = has_capability('mod/attendance:changeattendances', $this->context);
        }

        return $this->canchange;
    }

    public function can_manage() {
        if (is_null($this->canmanage)) {
            $this->canmanage = has_capability('mod/attendance:manageattendances', $this->context);
        }

        return $this->canmanage;
    }

    public function require_manage_capability() {
        require_capability('mod/attendance:manageattendances', $this->context);
    }

    public function can_change_preferences() {
        if (is_null($this->canchangepreferences)) {
            $this->canchangepreferences = has_capability('mod/attendance:changepreferences', $this->context);
        }

        return $this->canchangepreferences;
    }

    public function require_change_preferences_capability() {
        require_capability('mod/attendance:changepreferences', $this->context);
    }

    public function can_export() {
        if (is_null($this->canexport)) {
            $this->canexport = has_capability('mod/attendance:export', $this->context);
        }

        return $this->canexport;
    }

    public function require_export_capability() {
        require_capability('mod/attendance:export', $this->context);
    }

    public function can_be_listed() {
        if (is_null($this->canbelisted)) {
            $this->canbelisted = has_capability('mod/attendance:canbelisted', $this->context, null, false);
        }

        return $this->canbelisted;
    }

    public function can_access_all_groups() {
        if (is_null($this->canaccessallgroups)) {
            $this->canaccessallgroups = has_capability('moodle/site:accessallgroups', $this->context);
        }

        return $this->canaccessallgroups;
    }
}

class att_page_with_filter_controls {
    const SELECTOR_NONE         = 1;
    const SELECTOR_GROUP        = 2;
    const SELECTOR_SESS_TYPE    = 3;

    const SESSTYPE_COMMON       = 0;
    const SESSTYPE_ALL          = -1;
    const SESSTYPE_NO_VALUE     = -2;

    /** @var int current view mode */
    public $view;

    /** @var int $view and $curdate specify displaed date range */
    public $curdate;

    /** @var int start date of displayed date range */
    public $startdate;

    /** @var int end date of displayed date range */
    public $enddate;

    public $selectortype        = self::SELECTOR_NONE;

    protected $defaultview      = ATT_VIEW_WEEKS;

    private $cm;

    private $sessgroupslist;

    private $sesstype;

    public function init($cm) {
        $this->cm = $cm;
        $this->init_view();
        $this->init_curdate();
        $this->init_start_end_date();
    }

    private function init_view() {
        global $SESSION;

        if (isset($this->view)) {
            $SESSION->attcurrentattview[$this->cm->course] = $this->view;
        } else if (isset($SESSION->attcurrentattview[$this->cm->course])) {
            $this->view = $SESSION->attcurrentattview[$this->cm->course];
        } else {
            $this->view = $this->defaultview;
        }
    }

    private function init_curdate() {
        global $SESSION;

        if (isset($this->curdate)) {
            $SESSION->attcurrentattdate[$this->cm->course] = $this->curdate;
        } else if (isset($SESSION->attcurrentattdate[$this->cm->course])) {
            $this->curdate = $SESSION->attcurrentattdate[$this->cm->course];
        } else {
            $this->curdate = time();
        }
    }

    public function init_start_end_date() {
        global $CFG;

        // HOURSECS solves issue for weeks view with Daylight saving time and clocks adjusting by one hour backward.
        $date = usergetdate($this->curdate + HOURSECS);
        $mday = $date['mday'];
        $wday = $date['wday'] - $CFG->calendar_startwday;
        if ($wday < 0) {
            $wday += 7;
        }
        $mon = $date['mon'];
        $year = $date['year'];

        switch ($this->view) {
            case ATT_VIEW_DAYS:
                $this->startdate = make_timestamp($year, $mon, $mday);
                $this->enddate = make_timestamp($year, $mon, $mday + 1);
                break;
            case ATT_VIEW_WEEKS:
                $this->startdate = make_timestamp($year, $mon, $mday - $wday);
                $this->enddate = make_timestamp($year, $mon, $mday + 7 - $wday) - 1;
                break;
            case ATT_VIEW_MONTHS:
                $this->startdate = make_timestamp($year, $mon);
                $this->enddate = make_timestamp($year, $mon + 1);
                break;
            case ATT_VIEW_ALLPAST:
                $this->startdate = 1;
                $this->enddate = time();
                break;
            case ATT_VIEW_ALL:
                $this->startdate = 0;
                $this->enddate = 0;
                break;
        }
    }

    private function calc_sessgroupslist_sesstype() {
        global $SESSION;

        if (!array_key_exists('attsessiontype', $SESSION)) {
            $SESSION->attsessiontype = array($this->cm->course => self::SESSTYPE_ALL);
        } else if (!array_key_exists($this->cm->course, $SESSION->attsessiontype)) {
            $SESSION->attsessiontype[$this->cm->course] = self::SESSTYPE_ALL;
        }

        $group = optional_param('group', self::SESSTYPE_NO_VALUE, PARAM_INT);
        if ($this->selectortype == self::SELECTOR_SESS_TYPE) {
            if ($group > self::SESSTYPE_NO_VALUE) {
                $SESSION->attsessiontype[$this->cm->course] = $group;
                if ($group > self::SESSTYPE_ALL) {
                    // Set activegroup in $SESSION.
                    groups_get_activity_group($this->cm, true);
                } else {
                    // Reset activegroup in $SESSION.
                    unset($SESSION->activegroup[$this->cm->course][VISIBLEGROUPS][$this->cm->groupingid]);
                    unset($SESSION->activegroup[$this->cm->course]['aag'][$this->cm->groupingid]);
                    unset($SESSION->activegroup[$this->cm->course][SEPARATEGROUPS][$this->cm->groupingid]);
                }
                $this->sesstype = $group;
            } else {
                $this->sesstype = $SESSION->attsessiontype[$this->cm->course];
            }
        } else if ($this->selectortype == self::SELECTOR_GROUP) {
            if ($group == 0) {
                $SESSION->attsessiontype[$this->cm->course] = self::SESSTYPE_ALL;
                $this->sesstype = self::SESSTYPE_ALL;
            } else if ($group > 0) {
                $SESSION->attsessiontype[$this->cm->course] = $group;
                $this->sesstype = $group;
            } else {
                $this->sesstype = $SESSION->attsessiontype[$this->cm->course];
            }
        }

        if (is_null($this->sessgroupslist)) {
            $this->calc_sessgroupslist();
        }
        // For example, we set SESSTYPE_ALL but user can access only to limited set of groups.
        if (!array_key_exists($this->sesstype, $this->sessgroupslist)) {
            reset($this->sessgroupslist);
            $this->sesstype = key($this->sessgroupslist);
        }
    }

    private function calc_sessgroupslist() {
        global $USER, $PAGE;

        $this->sessgroupslist = array();
        $groupmode = groups_get_activity_groupmode($this->cm);
        if ($groupmode == NOGROUPS) {
            return;
        }

        if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $PAGE->context)) {
            $allowedgroups = groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid);
        } else {
            $allowedgroups = groups_get_all_groups($this->cm->course, $USER->id, $this->cm->groupingid);
        }

        if ($allowedgroups) {
            if ($groupmode == VISIBLEGROUPS or has_capability('moodle/site:accessallgroups', $PAGE->context)) {
                $this->sessgroupslist[self::SESSTYPE_ALL] = get_string('all', 'attendance');
            }
            if ($groupmode == VISIBLEGROUPS) {
                $this->sessgroupslist[self::SESSTYPE_COMMON] = get_string('commonsessions', 'attendance');
            }
            foreach ($allowedgroups as $group) {
                $this->sessgroupslist[$group->id] = format_string($group->name);
            }
        }
    }

    public function get_sess_groups_list() {
        if (is_null($this->sessgroupslist)) {
            $this->calc_sessgroupslist_sesstype();
        }

        return $this->sessgroupslist;
    }

    public function get_current_sesstype() {
        if (is_null($this->sesstype)) {
            $this->calc_sessgroupslist_sesstype();
        }

        return $this->sesstype;
    }

    public function set_current_sesstype($sesstype) {
        $this->sesstype = $sesstype;
    }
}

class att_view_page_params extends att_page_with_filter_controls {
    const MODE_THIS_COURSE  = 0;
    const MODE_ALL_COURSES  = 1;

    public $studentid;

    public $mode;

    public function  __construct() {
        $this->defaultview = ATT_VIEW_MONTHS;
    }

    public function get_significant_params() {
        $params = array();

        if (isset($this->studentid)) {
            $params['studentid'] = $this->studentid;
        }
        if ($this->mode != self::MODE_THIS_COURSE) {
            $params['mode'] = $this->mode;
        }

        return $params;
    }
}

class att_manage_page_params extends att_page_with_filter_controls {
    public function  __construct() {
        $this->selectortype = att_page_with_filter_controls::SELECTOR_SESS_TYPE;
    }

    public function get_significant_params() {
        return array();
    }
}

class att_sessions_page_params {
    const ACTION_ADD              = 1;
    const ACTION_UPDATE           = 2;
    const ACTION_DELETE           = 3;
    const ACTION_DELETE_SELECTED  = 4;
    const ACTION_CHANGE_DURATION  = 5;

    /** @var int view mode of taking attendance page*/
    public $action;
}

class att_take_page_params {
    const SORTED_LIST           = 1;
    const SORTED_GRID           = 2;

    const DEFAULT_VIEW_MODE     = self::SORTED_LIST;

    public $sessionid;
    public $grouptype;
    public $group;
    public $sort;
    public $copyfrom;

    /** @var int view mode of taking attendance page*/
    public $viewmode;

    public $gridcols;

    public function init() {
        if (!isset($this->group)) {
            $this->group = 0;
        }
        if (!isset($this->sort)) {
            $this->sort = ATT_SORT_LASTNAME;
        }
        $this->init_view_mode();
        $this->init_gridcols();
    }

    private function init_view_mode() {
        if (isset($this->viewmode)) {
            set_user_preference("attendance_take_view_mode", $this->viewmode);
        } else {
            $this->viewmode = get_user_preferences("attendance_take_view_mode", self::DEFAULT_VIEW_MODE);
        }
    }

    private function init_gridcols() {
        if (isset($this->gridcols)) {
            set_user_preference("attendance_gridcolumns", $this->gridcols);
        } else {
            $this->gridcols = get_user_preferences("attendance_gridcolumns", 5);
        }
    }

    public function get_significant_params() {
        $params = array();

        $params['sessionid'] = $this->sessionid;
        $params['grouptype'] = $this->grouptype;
        if ($this->group) {
            $params['group'] = $this->group;
        }
        if ($this->sort != ATT_SORT_LASTNAME) {
            $params['sort'] = $this->sort;
        }
        if (isset($this->copyfrom)) {
            $params['copyfrom'] = $this->copyfrom;
        }

        return $params;
    }
}

class att_report_page_params extends att_page_with_filter_controls {
    public $group;
    public $sort;

    public function  __construct() {
        $this->selectortype = self::SELECTOR_GROUP;
    }

    public function init($cm) {
        parent::init($cm);

        if (!isset($this->group)) {
            $this->group = $this->get_current_sesstype() > 0 ? $this->get_current_sesstype() : 0;
        }
        if (!isset($this->sort)) {
            $this->sort = ATT_SORT_LASTNAME;
        }
    }

    public function get_significant_params() {
        $params = array();

        if ($this->sort != ATT_SORT_LASTNAME) {
            $params['sort'] = $this->sort;
        }

        return $params;
    }
}

class att_preferences_page_params {
    const ACTION_ADD              = 1;
    const ACTION_DELETE           = 2;
    const ACTION_HIDE             = 3;
    const ACTION_SHOW             = 4;
    const ACTION_SAVE             = 5;

    /** @var int view mode of taking attendance page*/
    public $action;

    public $statusid;

    public function get_significant_params() {
        $params = array();

        if (isset($this->action)) {
            $params['action'] = $this->action;
        }
        if (isset($this->statusid)) {
            $params['statusid'] = $this->statusid;
        }

        return $params;
    }
}



class attendance {
    const SESSION_COMMON        = 0;
    const SESSION_GROUP         = 1;

    /** @var stdclass course module record */
    public $cm;

    /** @var stdclass course record */
    public $course;

    /** @var stdclass context object */
    public $context;

    /** @var int attendance instance identifier */
    public $id;

    /** @var string attendance activity name */
    public $name;

    /** @var float number (10, 5) unsigned, the maximum grade for attendance */
    public $grade;

    /** current page parameters */
    public $pageparams;

    /** @var attendance_permissions permission of current user for attendance instance*/
    public $perm;

    private $groupmode;

    private $statuses;

    /**
     * @var mixed Indicates the value of the last saved import record field.
     */
    private $lastimport;


    /**
     * @var array An array of user profile field short-names, which indicate the fields that can be used as ID numbers during import.
     */
    private $idnumberfields;

    // Cache

    // array by sessionid
    private $sessioninfo = array();

    // Arrays by userid.
    private $usertakensesscount = array();
    private $userstatusesstat = array();

    /**
     * Initializes the attendance API instance using the data from DB
     *
     * Makes deep copy of all passed records properties. Replaces integer $course attribute
     * with a full database record (course should not be stored in instances table anyway).
     *
     * @param stdClass $dbrecord Attandance instance data from {attendance} table
     * @param stdClass $cm       Course module record as returned by {@link get_coursemodule_from_id()}
     * @param stdClass $course   Course record from {course} table
     * @param stdClass $context  The context of the workshop instance
     */
    public function __construct(stdclass $dbrecord, stdclass $cm, stdclass $course, stdclass $context=null, $pageparams=null) {
        foreach ($dbrecord as $field => $value) {
            if (property_exists('attendance', $field)) {
                $this->{$field} = $value;
            } else {
                throw new coding_exception('The attendance table has a field with no property in the attendance class');
            }
        }
        $this->cm           = $cm;
        $this->course       = $course;
        if (is_null($context)) {
            $this->context = context_module::instance_by_id($this->cm->id);
        } else {
            $this->context = $context;
        }

        $this->pageparams = $pageparams;

        $this->perm = new attendance_permissions($this->cm, $this->context);
    }

    public function get_group_mode() {
        if (is_null($this->groupmode)) {
            $this->groupmode = groups_get_activity_groupmode($this->cm);
        }
        return $this->groupmode;
    }

    /**
     * Returns current sessions for this attendance
     *
     * Fetches data from {attendance_sessions}
     *
     * @return array of records or an empty array
     */
    public function get_current_sessions() {
        global $DB;

        $today = time(); // Because we compare with database, we don't need to use usertime().

        $sql = "SELECT *
                  FROM {attendance_sessions}
                 WHERE :time BETWEEN sessdate AND (sessdate + duration)
                   AND attendanceid = :aid";
        $params = array(
                'time'  => $today,
                'aid'   => $this->id);

        return $DB->get_records_sql($sql, $params);
    }

    /*
     * Returns today sessions for this attendance
     *
     * Fetches data from {attendance_sessions}
     *
     * @return array of records or an empty array
     */
    public function get_today_sessions() {
        global $DB;

        $start = usergetmidnight(time());
        $end = $start + DAYSECS;

        $sql = "SELECT *
                  FROM {attendance_sessions}
                 WHERE sessdate >= :start AND sessdate < :end
                   AND attendanceid = :aid";
        $params = array(
                'start' => $start,
                'end'   => $end,
                'aid'   => $this->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns today sessions suitable for copying attendance log
     *
     * Fetches data from {attendance_sessions}
     *
     * @return array of records or an empty array
     */
    public function get_today_sessions_for_copy($sess) {
        global $DB;

        $start = usergetmidnight($sess->sessdate);

        $sql = "SELECT *
                  FROM {attendance_sessions}
                 WHERE sessdate >= :start AND sessdate <= :end AND
                       (groupid = 0 OR groupid = :groupid) AND
                       lasttaken > 0 AND attendanceid = :aid";
        $params = array(
                'start'     => $start,
                'end'       => $sess->sessdate,
                'groupid'   => $sess->groupid,
                'aid'       => $this->id);

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Returns count of hidden sessions for this attendance
     *
     * Fetches data from {attendance_sessions}
     *
     * @return count of hidden sessions
     */
    public function get_hidden_sessions_count() {
        global $DB;

        $where = "attendanceid = :aid AND sessdate < :csdate";
        $params = array(
                'aid'   => $this->id,
                'csdate'=> $this->course->startdate);

        return $DB->count_records_select('attendance_sessions', $where, $params);
    }

    public function get_filtered_sessions() {
        global $DB;

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "attendanceid = :aid AND sessdate >= :csdate AND sessdate >= :sdate AND sessdate < :edate";
        } else {
            $where = "attendanceid = :aid AND sessdate >= :csdate";
        }
        if ($this->pageparams->get_current_sesstype() > att_page_with_filter_controls::SESSTYPE_ALL) {
            $where .= " AND groupid=:cgroup";
        }
        $params = array(
                'aid'       => $this->id,
                'csdate'    => $this->course->startdate,
                'sdate'     => $this->pageparams->startdate,
                'edate'     => $this->pageparams->enddate,
                'cgroup'    => $this->pageparams->get_current_sesstype());
        $sessions = $DB->get_records_select('attendance_sessions', $where, $params, 'sessdate asc');
        foreach ($sessions as $sess) {
            if (empty($sess->description)) {
                $sess->description = get_string('nodescription', 'attendance');
            } else {
                $sess->description = file_rewrite_pluginfile_urls($sess->description,
                        'pluginfile.php', $this->context->id, 'mod_attendance', 'session', $sess->id);
            }
        }

        return $sessions;
    }

    /**
     * @return moodle_url of manage.php for attendance instance
     */
    public function url_manage($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/manage.php', $params);
    }

    /**
     * @return moodle_url of sessions.php for attendance instance
     */
    public function url_sessions($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/sessions.php', $params);
    }

    /**
     * @return moodle_url of report.php for attendance instance
     */
    public function url_report($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/report.php', $params);
    }

    /**
     * @return moodle_url of export.php for attendance instance
     */
    public function url_export() {
        $params = array('id' => $this->cm->id);
        return new moodle_url('/mod/attendance/export.php', $params);
    }

    /**
     * @return moodle_url The URL used for the bulk import of data.
     */
    public function url_import() {
        $params = array('id' => $this->cm->id);
        return new moodle_url('/mod/attendance/import.php', $params);
    }

    /**
     * @return moodle_url of attsettings.php for attendance instance
     */
    public function url_preferences($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/preferences.php', $params);
    }

    /**
     * @return moodle_url of attendances.php for attendance instance
     */
    public function url_take($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/take.php', $params);
    }

    public function url_view($params=array()) {
        $params = array_merge(array('id' => $this->cm->id), $params);
        return new moodle_url('/mod/attendance/view.php', $params);
    }

    public function add_sessions($sessions) {
        global $DB;

        foreach ($sessions as $sess) {
            $sess->attendanceid = $this->id;

            $sess->id = $DB->insert_record('attendance_sessions', $sess);
            $description = file_save_draft_area_files($sess->descriptionitemid,
                        $this->context->id, 'mod_attendance', 'session', $sess->id,
                        array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0),
                        $sess->description);
            $DB->set_field('attendance_sessions', 'description', $description, array('id' => $sess->id));
        }

        $info_array = array();
        $maxlog = 7; // Only log first 10 sessions and last session in the log info. as we can only store 255 chars.
        $i = 0;
        foreach ($sessions as $sess) {
            if ($i > $maxlog) {
                $lastsession = end($sessions);
                $info_array[] = '...';
                $info_array[] = construct_session_full_date_time($lastsession->sessdate, $lastsession->duration);
                break;
            } else {
                $info_array[] = construct_session_full_date_time($sess->sessdate, $sess->duration);
            }
            $i++;
        }
        add_to_log($this->course->id, 'attendance', 'sessions added', $this->url_manage(),
            implode(',', $info_array), $this->cm->id);
    }

    public function update_session_from_form_data($formdata, $sessionid) {
        global $DB;

        if (!$sess = $DB->get_record('attendance_sessions', array('id' => $sessionid) )) {
            print_error('No such session in this course');
        }

        $sess->sessdate = $formdata->sessiondate;
        $sess->duration = $formdata->durtime['hours']*HOURSECS + $formdata->durtime['minutes']*MINSECS;
        $description = file_save_draft_area_files($formdata->sdescription['itemid'],
                                $this->context->id, 'mod_attendance', 'session', $sessionid,
                                array('subdirs' => false, 'maxfiles' => -1, 'maxbytes' => 0), $formdata->sdescription['text']);
        $sess->description = $description;
        $sess->descriptionformat = $formdata->sdescription['format'];
        $sess->timemodified = time();
        $DB->update_record('attendance_sessions', $sess);

        $url = $this->url_sessions(array('sessionid' => $sessionid, 'action' => att_sessions_page_params::ACTION_UPDATE));
        $info = construct_session_full_date_time($sess->sessdate, $sess->duration);
        add_to_log($this->course->id, 'attendance', 'session updated', $url, $info, $this->cm->id);
    }

    public function fill_empty_attendance_records($session, $status, $remarks = '', $user = null) {
    
        global $DB;

        // Get the group ID that corresponds to the given session, if one exists.
        $group = $DB->get_field('attendance_sessions', 'groupid', array('id' => $session), MUST_EXIST);

        // Get an array of all users in the given session.
        $users = $this->get_users($group);

        // For each of the given users...
        foreach($users as $user) {

            // If the user's attendance has not yet been recorded, record a status with the value given.
            if(!$DB->record_exists('attendance_log', array('sessionid' => $session, 'studentid' => $user->id))) {
                $this->save_user_attendance_record($user->id, $session, $status->id, $remarks, $user);
            }
        }
    }

   /**
    * Saves a student's attendance status.
    * 
    * @param int $studentid The User ID for the student whose attendance is being recorded.
    * @param int $statusid The ID number of the status to be recorded.
    * @param string $remarks Any remarks to be saved with the attendance check-off.
    * @param user $user The user recording the student's attendance. If omitted, the current user will be used.
    * @return void
    */
   public function save_user_attendance_record($studentid, $session, $statusid, $remarks = '', $user = null) {
        
        global $DB, $USER;

        //If no user object was provided, assume the current user is taking attendance.
        if($user === null) {
            $user = $USER;
        }

        // Create the bulk of the user's attendance data.
        $record = new stdClass();
        $record->studentid = $studentid;
        $record->statusid = $statusid;
        $record->statusset = $this->get_database_status_set();
        $record->remarks = $remarks;
        $record->sessionid = $session;
        $record->timetake = time();
        $record->takenby = $user->id;

        // If the user already has a status for the given session, get its ID.
        $id = $DB->get_field('attendance_log', 'id', array('studentid' => $studentid, 'sessionid' => $session));

        // If the no status existed, create a new status entry...
        if($id === false) {
            $DB->insert_record('attendance_log', $record, false);
        } 
        // .. otherwise, update the existing entry.
        else {

            // Add the existing ID to the record, so Moodle knows which record to update...
            $record->id = $id;

            // ... and update the appropriate record.
            $DB->update_record('attendance_log', $record);
        }
    }

    /**
     * Updates the time at which attendance was last taken.
     * 
     * @param user $user The user who is currently taking attendance.
     * @return void
     */
    public function update_session_attendance_time($session, $time = null, $user = null) {
        
        global $DB, $USER;

        // If no time was specified, use the current time.
        if($time === null) {
            $time = time();
        }

        // If no user was specified, use the currently logged in user.
        if($user === null) {
            $user = $USER;
        }

        // Update the session to note the last time attendance was taken.
        $rec = new stdClass();
        $rec->id = $session;
        $rec->lasttaken = $time;
        $rec->lasttakenby = $USER->id;
        $DB->update_record('attendance_sessions', $rec);
    }

    /**
     * @return string A comma-delimited serialization of the possible statuses for a given course.
     *  Used to maintain assigned point values even after a status change is effected.
     */
    protected function get_database_status_set() {
        return implode(',', array_keys( (array)$this->get_statuses() ));
    }


    /**
     * @return string The value of the persistent import text, which represents the most recently saved value of the 'userdata' field.
     */
    public function get_persistent_import_text() {
        return $this->lastimport;
    }

    /**
     * Sets the value of the 'persistent' import text. This function is used to store
     * the "user data" field, so it appears to persist between page views.
     * 
     * @param string $value The value of the 'userdata' field.
     * @return void
     */
    public function set_persistent_import_text($value) {
        
        global $DB;

        // Set the value of the local copy of the last import text. 
        $this->lastimport = $value;

        // Store the import text in the database for later use.
        $DB->set_field('attendance', 'lastimport', $value, array('id' => $this->id));

    }

    /**
     * Imports a single attendance record from the given line.
     * 
     * @param string $line The line to be imported.
     * @param int $defaulttime The default time to be applied to any import row with a missing time, as a unix timestamp.
     * @param string $defaultstatus The default status, in the form normally used for this import format.
     * @param bool $updatetime If set, the session's last update time will be set.
     * @return int The ID number of the session that was updated.
     *
     * TODO: Replace with configurable barcode record recognition.
     */
    public function import_attendance_record($line, $defaulttime, $defaultstatus, $updatetime = true) {

        // Parse the given line as a CSV record.
        $record = str_getcsv($line);

        //If this record only has a single element 
        if(count($record) == 1) {
            $data = $this->parse_attendance_record_raw($record, $defaulttime, $defaultstatus); 
        }
        // If the entry identifies itself as a barcode entry, parse it as a
        // Opticon barcode reader entry.
        else if(trim($record[1]) === "Codabar") {
            $data = $this->parse_attendance_record_opticon($record, $defaulttime, $defaultstatus);
        } 
        // If we can't find any method of parsing this line, raise an invalid import format line.
        else {
            throw new attendance_import_exception('invalidimportformat', 'attendance', '', $line);
        }

        // Retrieve the session which was underway at the given time.
        $session_id = $this->session_id_from_time($data->time);

        // If were weren't able to find a sessio, throw an exception.
        if($session_id === false) {
            throw new attendance_import_exception('invalidsession', 'attendance', '', $line);
        }

        //public function save_user_attendance_record($student_id, $session, $status, $remarks = '', $user = null) {
        $this->save_user_attendance_record($data->user->id, $session_id, $data->status->id, empty($data->remark) ? '' : $data->remark);

        // If the "update attendance time" option is set, update the given session's "attendance last taken" time.
        if($updatetime) {
            $this->update_session_attendance_time($session_id);
        }

        // Return the ID of the session that was updated.
        return $session_id;
    } 

    /**
     * Parses a raw list of attendance records.
     */ 
    protected function parse_attendance_record_raw($record, $defaulttime, $defaultstatus) {

        //Create a new data element, populated with the defaults.
        $data = new stdClass();
        $data->time = $defaulttime;
        $data->status = $defaultstatus;

        // Get the user object corresponding to the active user.
        $data->user = $this->get_user_from_id_number(trim($record[0]));

        // If no user was found, throw an "invalid user" exception.
        if(empty($data->user)) {
            throw new attendance_import_exception('invalidimportuser', 'attendance', '', implode(',', $record));
        }

        // Add a "checked off by scan" message without the date.
        $data->remark = get_string('barcodescan', 'attendance');

        //Return the raw data.
        return $data;
    
    }

    /**
     * Parses an attendance record generated by an Opticon scanner.
     * 
     * @param array $record The record parsed from the scanning device.
     * @param int $defaulttime The timestamp assumed for the given record, if no timestamp is provided in the record.
     * @param int $defaultstatus The status object for the status which should be assumed for any scanned record
     *  which does not contain an attendance status.
     * @return stdClass A record with at least the properties (user, date, status).
     */
    protected function parse_attendance_record_opticon($record, $defaulttime, $defaultstatus) {
    
        $data = new stdClass();

        // Get the user object corresponding to the active user.
        $data->user = $this->get_user_from_id_number(trim($record[0]));

        // If no user was found, throw an "invalid user" exception.
        if(empty($data->user)) {
            throw new attendance_import_exception('invalidimportuser', 'attendance', '', implode(',', $record));
        }

        // If the scanner was configured to provide a date/time of scan...
        if(!empty($record[2])) {

            // ... interpret the scan date.
            $date = DateTime::createFromFormat('d/m/Y H:i:s', trim($record[2]));

            // If we weren't able to interpret the date, throw an exception.
            if(!$date) {
                throw new attendance_import_exception('invalidimportdate', 'attendance', '', implode(',', $record));
            }

            // Otherwise, use the given date.
            $data->time = $date->getTimestamp();

            // Add a "checked off by scan" message with the date.
            $data->remark = get_string('barcodescandate', 'attendance', userdate($data->time));

        } else {

            // Otherwise, use the default date.
            $data->time = $defaulttime; 

            // Add a "checked off by scan" message without the date.
            $data->remark = get_string('barcodescan', 'attendance');

        }

        // If a status was provided with the scan-data...
        if(!empty($record[3])) {

            // ... parse it, and attempt to get an attendance status.
            $data->status = $this->status_from_string(trim($record[3])); 

            // If we weren't able to match a status object, throw an exception.
            if(empty($data->status)) {
                throw new attendance_import_exception('invalidimportstatus', 'attendance', '', implode(',', $record));
            }

        } else {

            // Otherwise, use the default status.
            $data->status = $defaultstatus;
        }

        // Return the newly created data object.
        return $data;
    }

    /**
     * Retrieves the session ID of any session that is occurring at a given time.
     * TODO: Add a filter for "with user enrolled"?
     * 
     * @param int $time The unix timestamp for the given time.
     * @return int The session's ID.
     */
    protected function session_id_from_time($time) {

        global $DB;

        // Determine which session should match. A valid session must meet these three conditions:
        // - It must have belong to this course-module.
        // - It must have started before the given time; and
        // - It must have ended before the given time.
        $sql = 'SELECT id FROM {attendance_sessions} 
                WHERE 
                    attendanceid = :instanceid AND
                    :time BETWEEN sessdate AND (sessdate + duration)
                ORDER BY (sessdate + duration)
                LIMIT 1';

        // Retrieve the relevant record from the database
        return $DB->get_field_sql($sql, array('instanceid' => $this->id, 'time' => $time));
    }

    /**
     * @return int The starting time for the most recent session.
     */
    public function most_recent_session_start() {

        global $DB;
        // Determine which session should match. A valid session must meet these three conditions:
        // - It must have belong to this course-module.
        // - It must have started before the given time; and
        // - It must have ended before the given time.
        $sql = 'SELECT sessdate FROM {attendance_sessions} 
                WHERE 
                    attendanceid = :instanceid AND
                    sessdate <= :time
                ORDER BY (sessdate + duration)
                LIMIT 1';

        // Retrieve the _most recent_ session's ID.
        return $DB->get_field_sql($sql, array('instanceid' => $this->id, 'time' => time()));
    }

    /**
     * Gets a status ('mark') ID from a representative string.
     * 
     * @param string $string The string to be parsed; should be either an abbreviation or full name. Case insensitive.
     * @return stdClass The status record for the given string; or null if no status could be found.
     */
    public function status_from_string($string) {

        // For each of the status objects provided...
        foreach($this->get_statuses() as $status) {

            // If the status matches the given acronym, return it.
            if(strcasecmp($string, $status->acronym) === 0) { 
                return $status;
            }
        }

        // Repeat the previous operation, but using the description.
        // Note that we use two separate loops for this, so we have a predicatable hirearchy:
        // i.e. acronyms will be matched first, then statuses.
        foreach($this->get_statuses() as $status) {

            // If the status matches the given description, return it.
            if(strcasecmp($string, $status->description) === 0) { 
                return $status;
            }
        }

        // If we didn't find a status object, return null.
        return null;
    }

    protected function session_from_date() {
        

    }

    /**
     * Returns a user object for a user with a matching ID number.
     * 
     * @param mixed $id_number 
     * @param string $user_fields 
     * @return void
     */
    protected function get_user_from_id_number($id_number, $user_fields = 'id') {
    
        global $CFG, $DB;

        // If the administrator has enabled use of the the core user ID numbers, attempt to find a user with the given core ID.
        if(!empty($CFG->attendance_useidnumbers)) {

            // Attempt to get a user with the given ID number.
            $user = $DB->get_record('user', array('idnumber' => $id_number), $user_fields);

            // If we found a user, return their information.
            if($user !== false) {
                return $user;
            }
        }

        // If a list of ID number fields has been provided, then use it.
        if(!empty($CFG->attendance_idnumberfields)) {

            // Get a list of custom profile fields that can contain identification numbers.
            $idnumberfields = array_map('trim', explode(',', $CFG->attendance_idnumberfields));
        }
        // Otherwise, don't try to use custom profile fields.
        else {
            $idnumberfields = false;
        }

        // If fields to search through have been provided, use them.
        if(!empty($idnumberfields)) 
        {

            // Break the definition down into individual fields...
            $user_fields = explode(',', $user_fields);

            // Prefix each field name with "u.", which identifies the user table.
            foreach($user_fields as &$field) {
                $field = 'u.'.$field;
            }

            // Merge the parameter back into a SQL-compatible list.
            $user_fields = implode(',', $user_fields);

            // Create the SQL statement which will be used to retrieve user-data by profile fields.
            $sql = '
                        SELECT '.$user_fields.' FROM 
                        {user} u,
                        {user_info_data} d,
                        {user_info_field} f
                    WHERE
                         f.shortname = :shortname AND
                         d.fieldid = f.id AND
                         u.id = d.userid AND
                         d.data = :idnumber 
            ';

            // Finally, check each of the given profile fields for a matching user.
            foreach($idnumberfields as $field) {

                // Query for any user that matches this ID number.
                $user = $DB->get_record_sql($sql, array('shortname' => $field, 'idnumber' => $id_number));

                // If we've found a user, return them.
                return $user;
            }
        }

        // If we haven't found a user by this point, return null.
        return null;
    }


    /**
     * Processes submission of the "take attendance" form.
     * 
     * @param stdClass $formdata The data extracted from the "take attendance" Moodleform.
     * @return void
     */
    public function take_from_form_data($formdata) {

        global $USER;

        // Create an empty array of users to update. 
        $to_update = array();

        // Convert the form-data to an array.
        $formdata = (array)$formdata;

        // Process each of the submitted fields.
        foreach($formdata as $key => $value) {

            //If the string starts with the word 'user', it's a user attendance record.
            if(substr($key, 0, 4) == 'user') {

                // Extract the student's userid...
                $student_id = substr($key, 4);

                // If remarks were provided, use them; otherwise, store an empty string.
                $remarks = array_key_exists('remarks'.$student_id, $formdata) ? $formdata['remarks'.$student_id] : '';
                
                // Save the student's attendance record.
                $this->save_user_attendance_record($student_id, $this->pageparams->sessionid, $value, $remarks);
        
                // And mark the student's grade as requiring an update.
                $to_update[] = $student_id;
            }
        }

        // Update the session, indicating that attendance has been taken.
        $this->update_session_attendance_time($this->pageparams->sessionid);

        // Update the user's grades in the gradebook.
        $this->update_users_grade($to_update);

        // TODO: move this out of the library functions.
        // Compute the URL that should be displayed after the attendance is processed...
        $params = array( 'sessionid' => $this->pageparams->sessionid, 'grouptype' => $this->pageparams->grouptype);
        $url = $this->url_take($params);

        // Log the attendance taken event. (TODO: replace these strings?)
        $this->log('attendance taken', $url, $USER->firstname.' '.$USER->lastname);

        // ... and redirect to it.
        redirect($this->url_manage(), get_string('attendancesuccess','attendance'));

    }

    /*
    public function take_from_form_data($formdata) {
        global $DB, $USER;
        // TODO: WARNING - $formdata is unclean - comes from direct $_POST - ideally needs a rewrite but we do some cleaning below.
        $statuses = implode(',', array_keys( (array)$this->get_statuses() ));
        $now = time();
        $sesslog = array();
        $formdata = (array)$formdata;
        foreach ($formdata as $key => $value) {
            if (substr($key, 0, 4) == 'user') {
                $sid = substr($key, 4);
                if (!(is_numeric($sid) && is_numeric($value))) { // Sanity check on $sid and $value.
                     print_error('nonnumericid', 'attendance');
                }
                $sesslog[$sid] = new stdClass();
                $sesslog[$sid]->studentid = $sid; // We check is_numeric on this above.
                $sesslog[$sid]->statusid = $value; // We check is_numeric on this above.
                $sesslog[$sid]->statusset = $statuses;
                $sesslog[$sid]->remarks = array_key_exists('remarks'.$sid, $formdata) ?
                                                      clean_param($formdata['remarks'.$sid], PARAM_TEXT) : '';
                $sesslog[$sid]->sessionid = $this->pageparams->sessionid;
                $sesslog[$sid]->timetaken = $now;
                $sesslog[$sid]->takenby = $USER->id;
            }
        }

        $dbsesslog = $this->get_session_log($this->pageparams->sessionid);
        foreach ($sesslog as $log) {
            if ($log->statusid) {
                if (array_key_exists($log->studentid, $dbsesslog)) {
                    $log->id = $dbsesslog[$log->studentid]->id;
                    $DB->update_record('attendance_log', $log);
                } else {
                    $DB->insert_record('attendance_log', $log, false);
                }
            }
        }

        $rec = new stdClass();
        $rec->id = $this->pageparams->sessionid;
        $rec->lasttaken = $now;
        $rec->lasttakenby = $USER->id;
        $DB->update_record('attendance_sessions', $rec);

        if ($this->grade != 0) {
            $this->update_users_grade(array_keys($sesslog));
        }

        $params = array(
                'sessionid' => $this->pageparams->sessionid,
                'grouptype' => $this->pageparams->grouptype);
        $url = $this->url_take($params);
        add_to_log($this->course->id, 'attendance', 'taken', $url, '', $this->cm->id);

        redirect($this->url_manage(), get_string('attendancesuccess', 'attendance'));
    }
 */



    /**
     * MDL-27591 will make this method obsolete.
     * TODO replace with the function from MDL-27591
     */
    public function get_users($groupid = 0) {
        global $DB;

        // Fields we need from the user table.
        $userfields = user_picture::fields('u').',u.username';

        if (isset($this->pageparams->sort) and ($this->pageparams->sort == ATT_SORT_FIRSTNAME)) {
            $orderby = "u.firstname ASC, u.lastname ASC";
        } else {
            $orderby = "u.lastname ASC, u.firstname ASC";
        }

        $users = get_enrolled_users($this->context, 'mod/attendance:canbelisted', $groupid, $userfields, $orderby);

        // Add a flag to each user indicating whether their enrolment is active.
        if (!empty($users)) {
            list($usql, $uparams) = $DB->get_in_or_equal(array_keys($users), SQL_PARAMS_NAMED, 'usid0');

            // CONTRIB-3549.
            $sql = "SELECT ue.userid, ue.status, ue.timestart, ue.timeend
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE ue.userid $usql
                           AND e.status = :estatus
                           AND e.courseid = :courseid
                  GROUP BY ue.userid, ue.status, ue.timestart, ue.timeend";
            $params = array_merge($uparams, array('estatus'=>ENROL_INSTANCE_ENABLED, 'courseid'=>$this->course->id));
            $enrolmentsparams = $DB->get_records_sql($sql, $params);

            foreach ($users as $user) {
                $users[$user->id]->enrolmentstatus = $enrolmentsparams[$user->id]->status;
                $users[$user->id]->enrolmentstart = $enrolmentsparams[$user->id]->timestart;
                $users[$user->id]->enrolmentend = $enrolmentsparams[$user->id]->timeend;
            }
        }

        return $users;
    }

    public function get_user($userid) {
        global $DB;

        $user = $DB->get_record('user', array('id' => $userid), '*', MUST_EXIST);

        $sql = "SELECT ue.userid, ue.status, ue.timestart, ue.timeend
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE ue.userid = :uid
                       AND e.status = :estatus
                       AND e.courseid = :courseid
              GROUP BY ue.userid, ue.status, ue.timestart, ue.timeend";
        $params = array('uid' => $userid, 'estatus'=>ENROL_INSTANCE_ENABLED, 'courseid'=>$this->course->id);
        $enrolmentsparams = $DB->get_record_sql($sql, $params);

        $user->enrolmentstatus = $enrolmentsparams->status;
        $user->enrolmentstart = $enrolmentsparams->timestart;
        $user->enrolmentend = $enrolmentsparams->timeend;

        return $user;
    }

    public function get_statuses($onlyvisible = true) {
        if (!isset($this->statuses)) {
            $this->statuses = att_get_statuses($this->id, $onlyvisible);
        }

        return $this->statuses;
    }

    public function get_session_info($sessionid) {
        global $DB;

        if (!array_key_exists($sessionid, $this->sessioninfo)) {
            $this->sessioninfo[$sessionid] = $DB->get_record('attendance_sessions', array('id' => $sessionid));
        }
        if (empty($this->sessioninfo[$sessionid]->description)) {
            $this->sessioninfo[$sessionid]->description = get_string('nodescription', 'attendance');
        } else {
            $this->sessioninfo[$sessionid]->description = file_rewrite_pluginfile_urls($this->sessioninfo[$sessionid]->description,
                        'pluginfile.php', $this->context->id, 'mod_attendance', 'session', $this->sessioninfo[$sessionid]->id);
        }
        return $this->sessioninfo[$sessionid];
    }

    public function get_sessions_info($sessionids) {
        global $DB;

        list($sql, $params) = $DB->get_in_or_equal($sessionids);
        $sessions = $DB->get_records_select('attendance_sessions', "id $sql", $params, 'sessdate asc');

        foreach ($sessions as $sess) {
            if (empty($sess->description)) {
                $sess->description = get_string('nodescription', 'attendance');
            } else {
                $sess->description = file_rewrite_pluginfile_urls($sess->description,
                            'pluginfile.php', $this->context->id, 'mod_attendance', 'session', $sess->id);
            }
        }

        return $sessions;
    }

    public function get_session_log($sessionid) {
        global $DB;

        return $DB->get_records('attendance_log', array('sessionid' => $sessionid), '', 'studentid,statusid,remarks,id');
    }

    public function get_user_stat($userid) {
        $ret = array();
        $ret['completed'] = $this->get_user_taken_sessions_count($userid);
        $ret['statuses'] = $this->get_user_statuses_stat($userid);

        return $ret;
    }

    public function get_user_taken_sessions_count($userid) {
        if (!array_key_exists($userid, $this->usertakensesscount)) {
            $this->usertakensesscount[$userid] = att_get_user_taken_sessions_count($this->id, $this->course->startdate, $userid);
        }
        return $this->usertakensesscount[$userid];
    }

    public function get_user_statuses_stat($userid) {
        global $DB;

        if (!array_key_exists($userid, $this->userstatusesstat)) {
            $qry = "SELECT al.statusid, count(al.statusid) AS stcnt
                      FROM {attendance_log} al
                      JOIN {attendance_sessions} ats
                        ON al.sessionid = ats.id
                     WHERE ats.attendanceid = :aid AND
                           ats.sessdate >= :cstartdate AND
                           al.studentid = :uid
                  GROUP BY al.statusid";
            $params = array(
                    'aid'           => $this->id,
                    'cstartdate'    => $this->course->startdate,
                    'uid'           => $userid);

            $this->userstatusesstat[$userid] = $DB->get_records_sql($qry, $params);
        }

        return $this->userstatusesstat[$userid];
    }

    public function get_user_grade($userid) {
        return att_get_user_grade($this->get_user_statuses_stat($userid), $this->get_statuses());
    }

    // For getting sessions count implemented simplest method - taken sessions.
    // It can have error if users don't have attendance info for some sessions.
    // In the future we can implement another methods:
    // * all sessions between user start enrolment date and now;
    // * all sessions between user start and end enrolment date.
    // While implementing those methods we need recalculate grades of all users
    // on session adding.
    public function get_user_max_grade($userid) {
        return att_get_user_max_grade($this->get_user_taken_sessions_count($userid), $this->get_statuses());
    }

    public function update_users_grade($userids) {
        $grades = array();

        foreach ($userids as $userid) {
            $grades[$userid] = new stdClass();
            $grades[$userid]->userid = $userid;
            $grades[$userid]->rawgrade = att_calc_user_grade_fraction($this->get_user_grade($userid),
                                                                      $this->get_user_max_grade($userid)) * $this->grade;
        }

        return grade_update('mod/attendance', $this->course->id, 'mod', 'attendance',
                            $this->id, 0, $grades);
    }

    public function get_user_filtered_sessions_log($userid) {
        global $DB;

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate AND
                      ats.sessdate >= :sdate AND ats.sessdate < :edate";
        } else {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate";
        }

        $sql = "SELECT ats.id, ats.sessdate, ats.groupid, al.statusid
                  FROM {attendance_sessions} ats
                  JOIN {attendance_log} al
                    ON ats.id = al.sessionid AND al.studentid = :uid
                 WHERE $where
              ORDER BY ats.sessdate ASC";

        $params = array(
                'uid'       => $userid,
                'aid'       => $this->id,
                'csdate'    => $this->course->startdate,
                'sdate'     => $this->pageparams->startdate,
                'edate'     => $this->pageparams->enddate);
        $sessions = $DB->get_records_sql($sql, $params);

        return $sessions;
    }

    public function get_user_filtered_sessions_log_extended($userid) {
        global $DB;

        // All taked sessions (including previous groups).

        $groups = array_keys(groups_get_all_groups($this->course->id, $userid));
        $groups[] = 0;
        list($gsql, $gparams) = $DB->get_in_or_equal($groups, SQL_PARAMS_NAMED, 'gid0');

        if ($this->pageparams->startdate && $this->pageparams->enddate) {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate AND
                      ats.sessdate >= :sdate AND ats.sessdate < :edate";
            $where2 = "ats.attendanceid = :aid2 AND ats.sessdate >= :csdate2 AND
                      ats.sessdate >= :sdate2 AND ats.sessdate < :edate2 AND ats.groupid $gsql";
        } else {
            $where = "ats.attendanceid = :aid AND ats.sessdate >= :csdate";
            $where2 = "ats.attendanceid = :aid2 AND ats.sessdate >= :csdate2 AND ats.groupid $gsql";
        }

        $sql = "SELECT ats.id, ats.groupid, ats.sessdate, ats.duration, ats.description, al.statusid, al.remarks
                  FROM {attendance_sessions} ats
                RIGHT JOIN {attendance_log} al
                    ON ats.id = al.sessionid AND al.studentid = :uid
                 WHERE $where
            UNION
                SELECT ats.id, ats.groupid, ats.sessdate, ats.duration, ats.description, al.statusid, al.remarks
                  FROM {attendance_sessions} ats
                LEFT JOIN {attendance_log} al
                    ON ats.id = al.sessionid AND al.studentid = :uid2
                 WHERE $where2
             ORDER BY sessdate ASC";

        $params = array(
                'uid'       => $userid,
                'aid'       => $this->id,
                'csdate'    => $this->course->startdate,
                'sdate'     => $this->pageparams->startdate,
                'edate'     => $this->pageparams->enddate,
                'uid2'       => $userid,
                'aid2'       => $this->id,
                'csdate2'    => $this->course->startdate,
                'sdate2'     => $this->pageparams->startdate,
                'edate2'     => $this->pageparams->enddate);
        $params = array_merge($params, $gparams);
        $sessions = $DB->get_records_sql($sql, $params);

        foreach ($sessions as $sess) {
            if (empty($sess->description)) {
                $sess->description = get_string('nodescription', 'attendance');
            } else {
                $sess->description = file_rewrite_pluginfile_urls($sess->description,
                        'pluginfile.php', $this->context->id, 'mod_attendance', 'session', $sess->id);
            }
        }

        return $sessions;
    }

    public function delete_sessions($sessionsids) {
        global $DB;

        list($sql, $params) = $DB->get_in_or_equal($sessionsids);
        $DB->delete_records_select('attendance_log', "sessionid $sql", $params);
        $DB->delete_records_list('attendance_sessions', 'id', $sessionsids);
        add_to_log($this->course->id, 'attendance', 'sessions deleted', $this->url_manage(),
            get_string('sessionsids', 'attendance').implode(', ', $sessionsids), $this->cm->id);
    }

    public function update_sessions_duration($sessionsids, $duration) {
        global $DB;

        $now = time();
        $sessions = $DB->get_records_list('attendance_sessions', 'id', $sessionsids);
        foreach ($sessions as $sess) {
            $sess->duration = $duration;
            $sess->timemodified = $now;
            $DB->update_record('attendance_sessions', $sess);
        }
        add_to_log($this->course->id, 'attendance', 'sessions duration updated', $this->url_manage(),
            get_string('sessionsids', 'attendance').implode(', ', $sessionsids), $this->cm->id);
    }

    public function remove_status($statusid) {
        global $DB;

        $DB->set_field('attendance_statuses', 'deleted', 1, array('id' => $statusid));
    }

    public function add_status($acronym, $description, $grade) {
        global $DB;

        if ($acronym && $description) {
            $rec = new stdClass();
            $rec->courseid = $this->course->id;
            $rec->attendanceid = $this->id;
            $rec->acronym = $acronym;
            $rec->description = $description;
            $rec->grade = $grade;
            $DB->insert_record('attendance_statuses', $rec);

            add_to_log($this->course->id, 'attendance', 'status added', $this->url_preferences(),
                $acronym.': '.$description.' ('.$grade.')', $this->cm->id);
        } else {
            print_error('cantaddstatus', 'attendance', $this->url_preferences());
        }
    }

    public function update_status($statusid, $acronym, $description, $grade, $visible) {
        global $DB;

        $updated = array();

        $status = new stdClass();
        $status->id = $statusid;
        if ($acronym) {
            $status->acronym = $acronym;
            $updated[] = $acronym;
        }
        if ($description) {
            $status->description = $description;
            $updated[] = $description;
        }
        if (isset($grade)) {
            $status->grade = $grade;
            $updated[] = $grade;
        }
        if (isset($visible)) {
            $status->visible = $visible;
            $updated[] = $visible ? get_string('show') : get_string('hide');
        }
        $DB->update_record('attendance_statuses', $status);

        add_to_log($this->course->id, 'attendance', 'status updated', $this->url_preferences(),
            implode(' ', $updated), $this->cm->id);
    }
}


function att_get_statuses($attid, $onlyvisible=true) {
    global $DB;

    if ($onlyvisible) {
        $statuses = $DB->get_records_select('attendance_statuses', "attendanceid = :aid AND visible = 1 AND deleted = 0",
                                            array('aid' => $attid), 'grade DESC');
    } else {
        $statuses = $DB->get_records_select('attendance_statuses', "attendanceid = :aid AND deleted = 0",
                                            array('aid' => $attid), 'grade DESC');
    }

    return $statuses;
}

function att_get_user_taken_sessions_count($attid, $coursestartdate, $userid) {
    global $DB;

    $qry = "SELECT count(*) as cnt
              FROM {attendance_log} al
              JOIN {attendance_sessions} ats
                ON al.sessionid = ats.id
             WHERE ats.attendanceid = :aid AND
                   ats.sessdate >= :cstartdate AND
                   al.studentid = :uid";
    $params = array(
            'aid'           => $attid,
            'cstartdate'    => $coursestartdate,
            'uid'           => $userid);

    return $DB->count_records_sql($qry, $params);
}

function att_get_user_statuses_stat($attid, $coursestartdate, $userid) {
    global $DB;

    $qry = "SELECT al.statusid, count(al.statusid) AS stcnt
              FROM {attendance_log} al
              JOIN {attendance_sessions} ats
                ON al.sessionid = ats.id
             WHERE ats.attendanceid = :aid AND
                   ats.sessdate >= :cstartdate AND
                   al.studentid = :uid
          GROUP BY al.statusid";
    $params = array(
            'aid'           => $attid,
            'cstartdate'    => $coursestartdate,
            'uid'           => $userid);

    return $DB->get_records_sql($qry, $params);
}

function att_get_user_grade($userstatusesstat, $statuses) {
    $sum = 0;
    foreach ($userstatusesstat as $stat) {
        $sum += $stat->stcnt * $statuses[$stat->statusid]->grade;
    }

    return $sum;
}

function att_get_user_max_grade($sesscount, $statuses) {
    reset($statuses);
    return current($statuses)->grade * $sesscount;
}

function att_get_user_courses_attendances($userid) {
    global $DB;

    $usercourses = enrol_get_users_courses($userid);

    list($usql, $uparams) = $DB->get_in_or_equal(array_keys($usercourses), SQL_PARAMS_NAMED, 'cid0');

    $sql = "SELECT att.id as attid, att.course as courseid, course.fullname as coursefullname,
                   course.startdate as coursestartdate, att.name as attname, att.grade as attgrade
              FROM {attendance} att
              JOIN {course} course
                   ON att.course = course.id
             WHERE att.course $usql
          ORDER BY coursefullname ASC, attname ASC";

    $params = array_merge($uparams, array('uid' => $userid));

    return $DB->get_records_sql($sql, $params);
}

function att_calc_user_grade_fraction($grade, $maxgrade) {
    if ($maxgrade == 0) {
        return 0;
    } else {
        return $grade / $maxgrade;
    }
}

function att_get_gradebook_maxgrade($attid) {
    global $DB;

    return $DB->get_field('attendance', 'grade', array('id' => $attid));
}

function att_update_all_users_grades($attid, $course, $context) {
    $grades = array();

    $userids = array_keys(get_enrolled_users($context, 'mod/attendance:canbelisted', 0, 'u.id'));

    $statuses = att_get_statuses($attid);
    $gradebook_maxgrade = att_get_gradebook_maxgrade($attid);
    foreach ($userids as $userid) {
        $grade = new stdClass;
        $grade->userid = $userid;
        $userstatusesstat = att_get_user_statuses_stat($attid, $course->startdate, $userid);
        $usertakensesscount = att_get_user_taken_sessions_count($attid, $course->startdate, $userid);
        $usergrade = att_get_user_grade($userstatusesstat, $statuses);
        $usermaxgrade = att_get_user_max_grade($usertakensesscount, $statuses);
        $grade->rawgrade = att_calc_user_grade_fraction($usergrade, $usermaxgrade) * $gradebook_maxgrade;
        $grades[$userid] = $grade;
    }

    return grade_update('mod/attendance', $course->id, 'mod', 'attendance',
                        $attid, 0, $grades);
}

function att_has_logs_for_status($statusid) {
    global $DB;

    return $DB->count_records('attendance_log', array('statusid'=> $statusid)) > 0;
}

function att_log_convert_url(moodle_url $fullurl) {
    static $baseurl;

    if (!isset($baseurl)) {
        $baseurl = new moodle_url('/mod/attendance/');
        $baseurl = $baseurl->out();
    }

    return substr($fullurl->out(), strlen($baseurl));
}

function attforblock_upgrade() {
    global $DB, $CFG;
    $module = $DB->get_record('modules', array('name' => 'attforblock'));
    if ($module->version <= '2011061800') {
        print_error("noupgradefromthisversion", 'attendance');
    }
    if (file_exists($CFG->dirroot.'/mod/attforblock')) {
        print_error("attendancedirstillexists", 'attendance');
    }

    // Now rename attforblock table and replace with attendance?
    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
    $table = new xmldb_table('attforblock');
    $newtable = new xmldb_table('attendance'); // Sanity check to make sure 'attendance' table doesn't already exist.
    if ($dbman->table_exists($table) && !$dbman->table_exists($newtable)) {
        $dbman->rename_table($table, 'attendance');
    } else {
        print_error("tablerenamefailed", 'attendance');
    }
    // Now convert module record.
    $module->name = 'attendance';
    $DB->update_record('modules', $module);

    // Clear cache for courses with attendances.
    $attendances = $DB->get_recordset('attendance', array(), '', 'course');
    foreach ($attendances as $attendance) {
        rebuild_course_cache($attendance->course, true);
    }
    $attendances->close();
}
