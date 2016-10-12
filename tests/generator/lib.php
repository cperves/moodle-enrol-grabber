<?php


/**
 * enrol_grabber data generator
 *
 * @package  
 * @subpackage 
 * @copyright  2016 unistra  {@link http://unistra.fr}
 * @author Celine Perves <cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

/**
 * enrol_grabber data generator class.
 *
 * @package    enrol_grabber
 * @category   test
 */
class enrol_grabber_generator extends testing_data_generator {

	public function create_enrol_instance($enrol, $fields,$course){
		//retrieve plugin to add instance
		$plugin = enrol_get_plugin($enrol);
		return $plugin->add_instance($course, $fields);
	}

}
