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
 * Adds new instance of enrol_grabber to specified course
 * or edits current instance.
 *
 * @package    enrol_grabber
 * @copyright  2016 Unistra {@link http://unistra.fr}
 * @author Celine Perves <cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class enrol_grabber_edit_form extends moodleform {

    function definition() {
        $mform = $this->_form;

        list($instance, $plugin, $context) = $this->_customdata;

        $mform->addElement('header', 'header', get_string('pluginname', 'enrol_grabber'));
        
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', 'enrol_grabber'), $options);
        $mform->addHelpButton('status', 'status', 'enrol_grabber');
        $mform->setDefault('status', $plugin->get_config('status'));

        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $plugin->get_config('roleid'));
        }
        $mform->addElement('select', 'roleid', get_string('defaultrole', 'role'), $roles);
        $mform->setDefault('roleid', $plugin->get_config('roleid'));

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
		
        $enrol_instances = enrol_get_instances($instance->courseid, false);
        $plugins   = enrol_get_plugins(false);
        $options = array();
        foreach($enrol_instances as $enrol_instance){
        	if(!$instance->id || ($instance->id && $enrol_instance->id != $instance->id)){
        		$plugin = $plugins[$enrol_instance->enrol];
        		$options[$enrol_instance->id] = $plugin->get_instance_name($enrol_instance);
        	}
        }
        if ($instance->id) {
        	$mform->addElement('text', 'customtext1', get_string('grabberenrolinstance', 'enrol_grabber'), $instance->customtext1);
        	$mform->addElement('hidden', 'customint1', $instance->customint1);
        }else if (count($options) == 0 ){
        	print_error(get_string('cantusewithoutinstances','enrol_grabber'));
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
        
        $this->add_action_buttons(true, ($instance->id ? null : get_string('addinstance', 'enrol')));

        $this->set_data($instance);
    }

    function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
		//customint1 must exists, have same course and not be grabber enrol method
		//can't grab an already grabbed instance
        list($instance, $plugin, $context) = $this->_customdata;
        if(!$instance->id){
			$results = $DB->get_records_sql('select * from {enrol} where courseid=:courseid and customint1=:customint1 and enrol=\'grabber\'', array('courseid'=> $data['courseid'], 'customint1'=> $data['customint1']));
			if(count($results)>0){
				$errors['customint1']=get_string('alreadygrabbedinstance', 'enrol_grabber');
			}
        }
        return $errors;
    }
    /**
     * override in order to put unchecked values to 0 instead of undefined
     * (non-PHPdoc)
     * @see moodleform::get_data()
     */
    function get_data() {
    	$datas = parent::get_data();
    	if(isset($datas)){
    		if(!property_exists($datas, 'customint2')){
    			$datas->customint2=0;
    		}
    	}
    	return $datas;
    	 
    }
}
