<?php

/**
 * grabber enrolment tests.
 *
 * @package    enrol_grabber
 * @category   phpunit
 * @copyright  2016 Unistra {@link http://nistra.fr}
 * @author Celine Perves <cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Grabber enrolment tests.
 *
 * @package    enrol_grabber
 * @category   phpunit
 * @copyright  2016 Unistra {@link http://unistra.fr}
 * @author Celine Perves <cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_grabber_lib_testcase extends advanced_testcase {
	public function test_grab_process() {
		global $DB, $CFG;
		require_once($CFG->dirroot.'/enrol/manual/locallib.php');
	
		$this->resetAfterTest();
		$localDataGenerator = $this->getDataGenerator()->get_plugin_generator('enrol_grabber');
	
		/** @var $manplugin enrol_manual_plugin */
		$man_plugin = enrol_get_plugin('manual');
		$self_plugin = enrol_get_plugin('self');
		$grabber_plugin = enrol_get_plugin('grabber');
		// Setup a few courses and users.
	
		$studentrole = $DB->get_record('role', array('shortname'=>'student'));
		$this->assertNotEmpty($studentrole);
		$teacherrole = $DB->get_record('role', array('shortname'=>'teacher'));
		$this->assertNotEmpty($teacherrole);
	
		$course1 = $this->getDataGenerator()->create_course();
	
		$context1 = context_course::instance($course1->id);
	
		$user1 = $this->getDataGenerator()->create_user();
		$user2 = $this->getDataGenerator()->create_user();
		$user3 = $this->getDataGenerator()->create_user();
		$user4 = $this->getDataGenerator()->create_user();
	
		//default enrol expected
		$default_enrols = array();
		$course_instances = enrol_get_instances($course1->id, false);
		$manual_instance = null;
		foreach($course_instances as $course_instance){
			if($course_instance->enrol == 'manual'){
				$manual_instance = $course_instance;
				break;
			}
		}
		if(!isset($manual_instance)){
			//create manual instance
			$fields = array(
					'status'          => ENROL_INSTANCE_ENABLED,
					'roleid'          => $studentrole->id,
		            'enrolperiod'     => 0,
		            'expirynotify'    => 0,
		            'notifyall'       => 0,
		            'expirythreshold' => 0);
			$instanceid = $localDataGenerator->create_enrol_instance('manual', $fields , $course1);
			$course_instances = enrol_get_instances($course1->id, false);
			$manual_instance = $course_instances[$instanceid];
			
		}
			
		// Enrol some users to manual instances.
		$manplugin = enrol_get_plugin('manual');
		$manplugin->enrol_user($manual_instance, $user1->id, $studentrole->id);
		$manplugin->enrol_user($manual_instance, $user2->id, $studentrole->id);
		$manplugin->enrol_user($manual_instance, $user3->id, $teacherrole->id);
		$manplugin->enrol_user($manual_instance, $user4->id, $teacherrole->id);
	
		$this->assertEquals(4, $DB->count_records('user_enrolments', array('enrolid'=> $manual_instance->id)));
		//role assignement
		$this->assertEquals(2,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=\'\' and itemid=0', array('contextid'=>$context1->id, 'roleid'=> $studentrole->id)));
		$this->assertEquals(2,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=\'\' and itemid=0', array('contextid'=>$context1->id, 'roleid'=> $teacherrole->id)));
		
		//create grabber instance attached to manual instance
		$grabberplugin = enrol_get_plugin('grabber');
		$fields = array(
				'status'			=> ENROL_INSTANCE_ENABLED,
				'roleid'			=> $studentrole->id,
				'customint1'		=> $manual_instance->id,
				'customint2'		=> 1,//backup delete mode
				'customtext1'		=> $manplugin->get_instance_name($manual_instance)
		);
		$grabber_instanceid = $grabberplugin->add_instance($course1, $fields);
		$course_instances = enrol_get_instances($course1->id, false);
		$grabber_instance = $course_instances[$grabber_instanceid];
		$this->assertEquals(0, $DB->count_records('user_enrolments', array('enrolid'=> $manual_instance->id)));
		$this->assertEquals(4, $DB->count_records('user_enrolments', array('enrolid'=> $grabber_instanceid)));
		$this->assertEquals(0,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $studentrole->id, 'component'=>'', 'itemid'=>0)));
		$this->assertEquals(0,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $teacherrole->id, 'component'=>'', 'itemid'=>0)));
		$this->assertEquals(2,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $studentrole->id, 'component'=>'enrol_grabber', 'itemid'=>$grabber_instanceid)));
		$this->assertEquals(2,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $teacherrole->id, 'component'=>'enrol_grabber', 'itemid'=>$grabber_instanceid)));
		
		//delete grabber instance 
		$grabberplugin->delete_instance($grabber_instance);
		$this->assertEquals(4, $DB->count_records('user_enrolments', array('enrolid'=> $manual_instance->id)));
		$this->assertEquals(2,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $studentrole->id, 'component'=>'', 'itemid'=>0)));
		$this->assertEquals(2,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $teacherrole->id, 'component'=>'', 'itemid'=>0)));
		
		//create grabber instance attached to manual instance
		$grabberplugin = enrol_get_plugin('grabber');
		$fields = array(
				'status'			=> ENROL_INSTANCE_ENABLED,
				'roleid'			=> $studentrole->id,
				'customint1'		=> $manual_instance->id,
				'customint2'		=> 0,//not backup delete mode
				'customtext1'		=> $manplugin->get_instance_name($manual_instance)
		);
		$grabber_instanceid = $grabberplugin->add_instance($course1, $fields);
		$course_instances = enrol_get_instances($course1->id, false);
		$grabber_instance = $course_instances[$grabber_instanceid];
		$this->assertEquals(0, $DB->count_records('user_enrolments', array('enrolid'=> $manual_instance->id)));
		$this->assertEquals(4, $DB->count_records('user_enrolments', array('enrolid'=> $grabber_instanceid)));
		$this->assertEquals(0,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $studentrole->id, 'component'=>'', 'itemid'=>0)));
		$this->assertEquals(0,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $teacherrole->id, 'component'=>'', 'itemid'=>0)));
		$this->assertEquals(2,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $studentrole->id, 'component'=>'enrol_grabber', 'itemid'=>$grabber_instanceid)));
		$this->assertEquals(2,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $teacherrole->id, 'component'=>'enrol_grabber', 'itemid'=>$grabber_instanceid)));
		
		//delete grabber instance
		$grabberplugin->delete_instance($grabber_instance);
		$this->assertEquals(0, $DB->count_records('user_enrolments', array('enrolid'=> $manual_instance->id)));
		$this->assertEquals(0,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $studentrole->id, 'component'=>'', 'itemid'=>0)));
		$this->assertEquals(0,$DB->count_records_sql('select count(1) from {role_assignments} where contextid=:contextid and roleid=:roleid and component=:component and itemid=:itemid', array('contextid'=>$context1->id, 'roleid'=> $teacherrole->id, 'component'=>'', 'itemid'=>0)));
		
		
	}
}
