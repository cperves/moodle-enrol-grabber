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
 * Auxiliary grabber user enrolment lib, the main purpose is to lower memory requirements...
 *
 * @package    enrol_grabber
 * @copyright  2016 Unistra {@link http://unistra.fr}
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @author Celine Perves <cperves@unistra.fr>
 * @copyright  2010 Sam Hemelryk
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->dirroot . '/enrol/manual/locallib.php');


/**
 * Enrol candidates.
 */
class enrol_grabber_potential_participant extends enrol_manual_potential_participant {
    protected $enrolid;

    public function __construct($name, $options) {
        $this->enrolid  = $options['enrolid'];
        parent::__construct($name, $options);
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['enrolid'] = $this->enrolid;
        $options['file']    = 'enrol/grabber/locallib.php';
        return $options;
    }
}

/**
 * Enrolled users.
 */
class enrol_grabber_current_participant extends enrol_manual_current_participant {

    protected function get_options() {
        $options = parent::get_options();
        $options['enrolid'] = $this->enrolid;
        $options['file']    = 'enrol/grabber/locallib.php';
        return $options;
    }
}

/**
 * A bulk operation for the manual enrolment plugin to edit selected users.
 *
 */
class enrol_grabber_editselectedusers_operation extends enrol_manual_editselectedusers_operation {

    public function process(course_enrolment_manager $manager, array $users, stdClass $properties) {
        global $DB, $USER;

        if (!has_capability("enrol/grabber:manage", $manager->get_context())) {
            return false;
        }

        // Get all of the user enrolment id's.
        $ueids = array();
        $instances = array();
        foreach ($users as $user) {
            foreach ($user->enrolments as $enrolment) {
                $ueids[] = $enrolment->id;
                if (!array_key_exists($enrolment->id, $instances)) {
                    $instances[$enrolment->id] = $enrolment;
                }
            }
        }

        // Check that each instance is manageable by the current user.
        foreach ($instances as $instance) {
            if (!$this->plugin->allow_manage($instance)) {
                return false;
            }
        }

        // Collect the known properties.
        $status = $properties->status;

        list($ueidsql, $params) = $DB->get_in_or_equal($ueids, SQL_PARAMS_NAMED);

        $updatesql = array();
        if ($status == ENROL_USER_ACTIVE || $status == ENROL_USER_SUSPENDED) {
            $updatesql[] = 'status = :status';
            $params['status'] = (int)$status;
        }
         if (empty($updatesql)) {
            return true;
        }

        // Update the modifierid.
        $updatesql[] = 'modifierid = :modifierid';
        $params['modifierid'] = (int)$USER->id;

        // Update the time modified.
        $updatesql[] = 'timemodified = :timemodified';
        $params['timemodified'] = time();

        // Build the SQL statement.
        $updatesql = join(', ', $updatesql);
        $sql = "UPDATE {user_enrolments}
                   SET $updatesql
                 WHERE id $ueidsql";

        if ($DB->execute($sql, $params)) {
            foreach ($users as $user) {
                foreach ($user->enrolments as $enrolment) {
                    $enrolment->courseid  = $enrolment->enrolmentinstance->courseid;
                    $enrolment->enrol     = 'grabber';
                    // Trigger event.
                    $event = \core\event\user_enrolment_updated::create(
                            array(
                                'objectid' => $enrolment->id,
                                'courseid' => $enrolment->courseid,
                                'context' => context_course::instance($enrolment->courseid),
                                'relateduserid' => $user->id,
                                'other' => array('enrol' => 'grabber')
                                )
                            );
                    $event->trigger();
                }
            }
            // Delete cached course contacts for this course because they may be affected.
            cache::make('core', 'coursecontacts')->delete($manager->get_context()->instanceid);
            return true;
        }

        return false;
    }

    /**
     * Returns a enrol_bulk_enrolment_operation extension form to be used
     * in collecting required information for this operation to be processed.
     *
     * @param string|moodle_url|null $defaultaction
     * @param mixed $defaultcustomdata
     * @return enrol_manual_editselectedusers_form
     */
    public function get_form($defaultaction = null, $defaultcustomdata = null) {
        global $CFG;
        require_once($CFG->dirroot.'/enrol/grabber/bulkchangeforms.php');
        return new enrol_manual_editselectedusers_form($defaultaction, $defaultcustomdata);
    }
}


/**
 * A bulk operation for the manual enrolment plugin to delete selected users enrolments.
 *
 * @copyright 2011 Sam Hemelryk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_grabber_deleteselectedusers_operation extends enrol_manual_deleteselectedusers_operation {

    /**
     * Returns a enrol_bulk_enrolment_operation extension form to be used
     * in collecting required information for this operation to be processed.
     *
     * @param string|moodle_url|null $defaultaction
     * @param mixed $defaultcustomdata
     * @return enrol_manual_editselectedusers_form
     */
    public function get_form($defaultaction = null, $defaultcustomdata = null) {
        global $CFG;
        require_once($CFG->dirroot.'/enrol/manual/bulkchangeforms.php');
        if (!array($defaultcustomdata)) {
            $defaultcustomdata = array();
        }
        $defaultcustomdata['title'] = $this->get_title();
        $defaultcustomdata['message'] = get_string('confirmbulkdeleteenrolment', 'enrol_manual');
        $defaultcustomdata['button'] = get_string('unenrolusers', 'enrol_grabber');
        return new enrol_manual_deleteselectedusers_form($defaultaction, $defaultcustomdata);
    }

}


class enrol_grabber_utilities{
	/**
	 * Migrates all enrolments of the given plugin to enrol_grabber plugin,
	 *
	 * NOTE: this function does not trigger role and enrolment related events.
	 *
	 * @param string $enrol  The enrolment method.
	 */
	public static function grab_plugin_enrolments($grabber_instance, $instance_tograb) {
		global $DB;
			
		$grabber_plugin = enrol_get_plugin('grabber');
		$course_context = context_course::instance($grabber_instance->courseid);
	
		$params = array('enrol'=>$instance_tograb->id, 'courseid' => $grabber_instance->courseid);
		$sql = "SELECT e.id, e.courseid, e.status
	              FROM {enrol} e
	              JOIN {user_enrolments} ue ON (ue.enrolid = e.id)
	              JOIN {course} c ON (c.id = e.courseid)
	             WHERE e.id = :enrol and c.id=:courseid
	          GROUP BY e.id, e.courseid, e.status
	          ORDER BY e.id";
		$tograb_enrolles_rs = $DB->get_recordset_sql($sql, $params);
	
		foreach($tograb_enrolles_rs as $enrolment_tograb) {
			// First delete potential role duplicates.
			//component empty for self and manual so can only check that for the instance with itemid and component
			$component_to_grab = $instance_tograb->enrol=='manual' || $instance_tograb->enrol == 'self'?'' : 'enrol_'.$instance_tograb->enrol;
			$itemid_tograb = $instance_tograb->enrol=='manual' || $instance_tograb->enrol == 'self'? 0 : $enrolment_tograb->id;
			$grabber_component = $grabber_instance->enrol=='manual' || $grabber_instance->enrol == 'self'?'' : 'enrol_'.$grabber_instance->enrol;
			$grabber_itemid = $grabber_instance->enrol=='manual' || $grabber_instance->enrol == 'self'? 0 : $grabber_instance->id;
			$params = array('itemidtograb'=> $itemid_tograb, 'componenttograb'=> $component_to_grab, 'grabbercomponent'=>$grabber_component,'grabberitemid'=>$grabber_itemid, 'courseid' => $instance_tograb->courseid);
			$sql = "SELECT ra.id
	                  FROM {role_assignments} ra
	                  JOIN {role_assignments} mra ON (mra.contextid = ra.contextid AND mra.userid = ra.userid AND mra.roleid = ra.roleid AND mra.component = :grabbercomponent AND mra.itemid = :grabberitemid)
					  JOIN {context} ctx on ctx.id=ra.contextid
	                 WHERE ra.component = :componenttograb AND ra.itemid = :itemidtograb and ctx.instanceid=:courseid";
			$ras = $DB->get_records_sql($sql, $params);
			$ras = array_keys($ras);
			$DB->delete_records_list('role_assignments', 'id', $ras);
			unset($ras);
	
			// Migrate roles.
			$sql = "UPDATE {role_assignments}
	                   SET itemid = :grabberitemid, component = :grabbercomponent
	                 WHERE itemid = :itemidtograb AND component = :componenttograb and contextid= :coursecontext";
			$params = array('grabbercomponent' => $grabber_component, 'grabberitemid' => $grabber_itemid,'itemidtograb' => $itemid_tograb, 'componenttograb' => $component_to_grab, 'coursecontext' => $course_context->id);
			$DB->execute($sql, $params);
	
			// Delete potential enrol duplicates.
			$params = array('id'=>$enrolment_tograb->id, 'mid'=>$grabber_instance->id);
			$sql = "SELECT ue.id
	                  FROM {user_enrolments} ue
	                  JOIN {user_enrolments} mue ON (mue.userid = ue.userid AND mue.enrolid = :mid)
	                 WHERE ue.enrolid = :id";
			$ues = $DB->get_records_sql($sql, $params);
			$ues = array_keys($ues);
			$DB->delete_records_list('user_enrolments', 'id', $ues);
			unset($ues);
	
			// Migrate to grabber enrol instance.
			$params = array('id'=>$enrolment_tograb->id, 'mid'=>$grabber_instance->id);
			$sql = "UPDATE {user_enrolments}
			SET enrolid = :mid 
			WHERE enrolid = :id";
			$DB->execute($sql, $params);
		}
		$tograb_enrolles_rs->close();
	}
}
