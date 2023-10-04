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
 * grabber enrolment plugin main library file.
 *
 * @package    enrol_grabber
 * @author Celine Perves <cperves@unistra.fr>
 * @author Matthieu Fuchs <matfuchs@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class enrol_grabber_plugin extends enrol_plugin {

    protected $lasternoller = null;
    protected $lasternollerinstanceid = 0;

    public function roles_protected() {
        // Users may tweak the roles later.
        return false;
    }

    public function allow_enrol(stdClass $instance) {
        // Users with enrol cap may unenrol other users manually manually.
        return true;
    }

    public function allow_unenrol(stdClass $instance) {
        // Users with unenrol cap may unenrol other users manually manually.
        return true;
    }

    public function allow_manage(stdClass $instance) {
        // Users with manage cap may tweak period and status.
        return true;
    }

    /**
     * Returns link to grabber enrol UI if exists.
     * Does the access control tests automatically.
     *
     * @param stdClass $instance
     * @return moodle_url
     */
    public function get_manual_enrol_link($instance) {
        $name = $this->get_name();
        if ($instance->enrol !== $name) {
            throw new coding_exception('invalid enrol instance!');
        }

        if (!enrol_is_enabled($name)) {
            return NULL;
        }

        $context = context_course::instance($instance->courseid, MUST_EXIST);

        if (!has_capability('enrol/grabber:enrol', $context)) {
            // Note: manage capability not used here because it is used for editing
            // of existing enrolments which is not possible here.
            return NULL;
        }

        return new moodle_url('/enrol/grabber/manage.php', array('enrolid'=>$instance->id, 'id'=>$instance->courseid));
    }

    /**
     * Return true if we can add a new instance to this course.
     *
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        global $DB;

        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/manual:config', $context)) {
            return false;
        }
        return true;
    }

    /**
     * Returns edit icons for the page with list of instances.
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;

        if ($instance->enrol !== 'grabber') {
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);

        $icons = array();
        if (has_capability('enrol/grabber:enrol', $context) or has_capability('enrol/grabber:unenrol', $context)) {
            $managelink = new moodle_url("/enrol/grabber/manage.php", array('enrolid'=>$instance->id));
            $icons[] = $OUTPUT->action_icon($managelink, new pix_icon('t/enrolusers', get_string('enrolusers', 'enrol_grabber'), 'core', array('class'=>'iconsmall')));
        }
        $parenticons = parent::get_action_icons($instance);
        $icons = array_merge($icons, $parenticons);

        return $icons;
    }

    /**
     * Add new instance of enrol plugin with default settings.
     * @param stdClass $course
     * @return int id of new instance, null if can not be created
     */
    public function add_default_instance($course) {
        $fields = array(
            'status'          => $this->get_config('status'),
            'roleid'          => $this->get_config('roleid', 0),
        );
        return $this->add_instance($course, $fields);
    }

    /**
     * Returns a button to manually enrol users through the grabber enrolment plugin.
     *
     * TODO see how to manage multiple instances
     * If no manual enrolment instances exist within the course then false is returned.
     *
     * This function also adds a quickenrolment JS ui to the page so that users can be enrolled
     * via AJAX.
     *
     * @param course_enrolment_manager $manager
     * @return enrol_user_button
     */
    public function get_manual_enrol_button(course_enrolment_manager $manager) {
        global $CFG, $PAGE;
        require_once($CFG->dirroot.'/cohort/lib.php');

        static $called = false;

        $instance = null;
        $instances = array();
        foreach ($manager->get_enrolment_instances() as $tempinstance) {
            if ($tempinstance->enrol == 'grabber') {
                if ($instance === null) {
                    $instance = $tempinstance;
                }
                $instances[] = array('id' => $tempinstance->id, 'name' => $this->get_instance_name($tempinstance));
            }
        }
        if (empty($instances)) {
            return false;
        }

        $link = $this->get_manual_enrol_link($instance);
        if (!$link) {
            return false;
        }

        $button = new enrol_user_button($link, get_string('enrolusers', 'enrol_grabber'), 'get');
        $button->class .= ' enrol_grabber_plugin';
        $button->primary = true;

        $context = context_course::instance($instance->courseid);
        $arguments = array('contextid' => $context->id);

        if (!$called) {
            $called = true;
            // Calling the following more than once will cause unexpected results.
            $PAGE->requires->js_call_amd('enrol_manual/quickenrolment', 'init', array($arguments));
        }

        return $button;
    }


    /**
     * Returns the user who is responsible for grabber enrolments in given instance.
     *
     * Usually it is the first editing teacher - the person with "highest authority"
     * as defined by sort_by_roleassignment_authority() having 'enrol/grabber:manage'
     * capability.
     *
     * @param int $instanceid enrolment instance id
     * @return stdClass user record
     */
    protected function get_enroller($instanceid) {
        global $DB;

        if ($this->lasternollerinstanceid == $instanceid and $this->lasternoller) {
            return $this->lasternoller;
        }

        $instance = $DB->get_record('enrol', array('id'=>$instanceid, 'enrol'=>$this->get_name()), '*', MUST_EXIST);
        $context = context_course::instance($instance->courseid);

        if ($users = get_enrolled_users($context, 'enrol/grabber:manage')) {
            $users = sort_by_roleassignment_authority($users, $context);
            $this->lasternoller = reset($users);
            unset($users);
        } else {
            $this->lasternoller = parent::get_enroller($instanceid);
        }

        $this->lasternollerinstanceid = $instanceid;

        return $this->lasternoller;
    }

    /**
     * The grabber plugin has several bulk operations that can be performed.
     * @param course_enrolment_manager $manager
     * @return array
     */
    public function get_bulk_operations(course_enrolment_manager $manager) {
        global $CFG;
        require_once($CFG->dirroot.'/enrol/grabber/locallib.php');
        $context = $manager->get_context();
        $bulkoperations = array();
        if (has_capability("enrol/grabber:manage", $context)) {
            $bulkoperations['editselectedusers'] = new enrol_grabber_editselectedusers_operation($manager, $this);
        }
        if (has_capability("enrol/grabber:manage", $context)) {
            $bulkoperations['deleteselectedusers'] = new enrol_grabber_deleteselectedusers_operation($manager, $this);
        }
        return $bulkoperations;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = array(
                'courseid'      => $data->courseid,
                'enrol'         => $this->get_name(),
                'roleid'        => $data->roleid,
                'customint1'    => $data->customint1,
                'customtext1'   => $data->customtext1,
                'customint2'    => $data->customint2
            );
        }
        if ($merge and $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }

    /**
     * Restore role assignment.
     *
     * @param stdClass $instance
     * @param int $roleid
     * @param int $userid
     * @param int $contextid
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid) {
        // This is necessary only because we may migrate other types to this instance,
        // we do not use component in manual or self enrol.
        role_assign($roleid, $userid, $contextid, '', 0);
    }

    /**
     * Restore user group membership.
     * @param stdClass $instance
     * @param int $groupid
     * @param int $userid
     */
    public function restore_group_member($instance, $groupid, $userid) {
        global $CFG;
        require_once("$CFG->dirroot/group/lib.php");

        // This might be called when forcing restore as manual enrolments.

        groups_add_member($groupid, $userid);
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/grabber:delete', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/grabber:config', $context);
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'));
        return $options;
    }

    /**
     * Return an array of valid options for the roleid.
     *
     * @param stdClass $instance
     * @param context $context
     * @return array
     */
    protected function get_roleid_options($instance, $context) {
        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $this->get_config('roleid'));
        }
        return $roles;
    }

    /**
     * override
     * (non-PHPdoc)
     * @see enrol_plugin::get_instance_name()
     */
    public function get_instance_name($instance) {
        global $DB;
        if (empty($instance->name)) {
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol) .' ('. $instance->customtext1.')';
        } else {
            return format_string($instance->name);
        }
    }
    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_grabber'), $options);
        $mform->addHelpButton('status', 'status', 'enrol_manual');
        $mform->setDefault('status', $this->get_config('status'));

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $this->get_config('roleid'));
        }
        $mform->addElement('select', 'roleid', get_string('defaultrole', 'role'), $roles);
        $mform->setDefault('roleid', $this->get_config('roleid'));
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $enrol_instances = enrol_get_instances($instance->courseid, false);
        $plugins   = enrol_get_plugins(false);
        $options = array();
        // When restoring in new course no enrol instance can be possible so -1 value
        if ($instance->id && $instance->customint1 == -1) {
            $options[-1] = $instance->customtext1;
        } else {
            if (defined('BEHAT_SITE_RUNNING') && !$instance->id) {
                $options[-1] = get_string('notattachedgrabber', 'enrol_grabber');
            }
            foreach($enrol_instances as $enrol_instance){
                if(!$instance->id || ($instance->id && $enrol_instance->id != $instance->id)){
                    $plugin = $plugins[$enrol_instance->enrol];
                    $options[$enrol_instance->id] = $plugin->get_instance_name($enrol_instance);
                }
            }
        }
        if ($instance->id) {
            $mform->addElement('text', 'customtext1', get_string('grabberenrolinstance', 'enrol_grabber'), $options[$instance->customint1]);
            $mform->addElement('hidden', 'customint1', $instance->customint1);
        }else if (count($options) == 0 ){
            throw new moodle_exception(get_string('cantusewithoutinstances','enrol_grabber'));
        }else{
            $mform->addElement('select', 'customint1', get_string('grabberenrolinstance', 'enrol_grabber'), $options);
        }
        //not modifying associateenrolinstance
        //associateenrolinstance is the id of the grabber enrol instance, for same course
        $mform->setType('customint1', PARAM_INT);
        $mform->setType('customtext1', PARAM_RAW);

        if($instance->id){
            $mform->hardFreeze('customtext1');
        }
        $mform->addElement('checkbox','customint2',get_string('deleteback','enrol_grabber'));
        $mform->setType('customint2', PARAM_INT);
        $mform->setDefault('customint2', get_config('enrol_grabber','deleteback'));

        if (enrol_accessing_via_instance($instance)) {
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'), get_string('instanceeditselfwarningtext', 'core_enrol'));
        }

    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        global $DB;
        $errors = array();
        //customint1 must exists, have same course and not be grabber enrol method
        //can't grab an already grabbed instance
        if(!$instance->id){
            $results = $DB->get_records_sql('select * from {enrol} where courseid=:courseid and customint1=:customint1 and enrol=\'grabber\'', array('courseid'=> $data['courseid'], 'customint1'=> $data['customint1']));
            if(count($results)>0){
                $errors['customint1']=get_string('alreadygrabbedinstance', 'enrol_grabber');
            }
        }
        return $errors;
    }

    /**
     * Delete course enrol plugin instance, unenrol all users.
     * @param object $instance
     * @return void
     */
    public function delete_instance($instance) {
        global $CFG;
        require_once($CFG->dirroot.'/enrol/grabber/locallib.php');
        if($instance->customint2==1){
            //before delete instance give back enrollees to associated enrol instance if already exists
            $course_instances = enrol_get_instances($instance->courseid, false);
            $associated_instance  = $course_instances[$instance->customint1];
            if($associated_instance ){
                enrol_grabber_utilities::grab_plugin_enrolments($associated_instance, $instance);
            }
        }
        parent::delete_instance($instance);
    }

    public function add_instance($course, array $fields = NULL) {
        global $CFG;
        require_once($CFG->dirroot.'/enrol/grabber/locallib.php');
        $course_instances = enrol_get_instances($course->id, false);
        $instancetograb = null;
        $plugins   = enrol_get_plugins(false);
        if (array_key_exists($fields['customint1'], $course_instances) && !empty($fields['customint1'])) {
            $instancetograb = $course_instances[$fields['customint1']];
            $plugin = $plugins[$instancetograb->enrol];
            $fields['customtext1'] = $plugin->get_instance_name($instancetograb);
        } else {
            $fields['customtext1'] =
                (!empty($fields['customtext1'])?
                    get_string('notattachedgrabberpreviouslya','enrol_grabber',$fields['customtext1'])
                    : get_string('notattachedgrabber', 'enrol_grabber'));
            $fields['customint1'] = -1;
        }
        // grab enrolles from other instance
        $grabber_instanceid = parent::add_instance($course, $fields);
        $course_instances = enrol_get_instances($course->id, false);
        $grabber_instance = $course_instances[$grabber_instanceid];
        //grab enrollments
        enrol_grabber_utilities::grab_plugin_enrolments($grabber_instance, $instancetograb);
        return $grabber_instanceid;

    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }
}
