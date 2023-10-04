<?php


/**
 * Adds new instance of enrol_manual to specified course
 * or edits current instance.
 *
 * @package    enrol_grabber
 * @copyright  2016 Unistra {@link http://unistra.fr}
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @author Celine Perves <cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once('edit_form.php');
require_once('locallib.php');

$courseid = required_param('courseid', PARAM_INT);
$instanceid = optional_param('id', 0, PARAM_INT);

$course = $DB->get_record('course', array('id'=>$courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('enrol/grabber:config', $context);

$PAGE->set_url('/enrol/grabber/edit.php', array('courseid'=>$course->id));
$PAGE->set_pagelayout('admin');

$return = new moodle_url('/enrol/instances.php', array('id'=>$course->id));
if (!enrol_is_enabled('grabber')) {
    redirect($return);
}

$plugin = enrol_get_plugin('grabber');

if ($instanceid) {
    $instance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'grabber', 'id'=>$instanceid), '*', MUST_EXIST);
} else {
    require_capability('moodle/course:enrolconfig', $context);
    // No instance yet, we have to add new instance.
    navigation_node::override_active_url(new moodle_url('/enrol/instances.php', array('id'=>$course->id)));
    $instance = new stdClass();
    $instance->id              = null;
    $instance->courseid        = $course->id;
}

$mform = new enrol_grabber_edit_form(null, array($instance, $plugin, $context));

if ($mform->is_cancelled()) {
    redirect($return);

} else if ($data = $mform->get_data()) {
    if ($instance->id) {
        $instance->roleid               = $data->roleid;
        $instance->customint2          = !isset($data->customint2)?0:$data->customint2;
        $instance->name                    = $data->name;
        $instance->timemodified    = time();
        $markdirty = ($instance->status != $data->status);
        $instance->status = $data->status;

        $DB->update_record('enrol', $instance);
        \core\event\enrol_instance_updated::create_from_record($instance)->trigger();

        if ($markdirty) {
            $context->mark_dirty();
        }

    } else {
         //retrieve customint2 value
         $course_instances = enrol_get_instances($courseid, false);
         $instance_tograb = $course_instances[$data->customint1];
         $plugins   = enrol_get_plugins(false);
         $tograb_plugin = $plugins[$instance_tograb->enrol];
        $fields = array(
            'status'               => $data->status,
            'roleid'               => $data->roleid,
             'customint1'          => $data->customint1,
             'customint2'          => $data->customint2,
             'customtext1'          => $tograb_plugin->get_instance_name($instance_tograb)
            );
        $plugin->add_instance($course, $fields);     
    }

    redirect($return);
}

$PAGE->set_title(get_string('pluginname', 'enrol_grabber'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'enrol_grabber'));
$mform->display();
echo $OUTPUT->footer();
