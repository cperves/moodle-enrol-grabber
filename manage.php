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
 * grabber user enrolment UI.
 *
 * @package    enrol_grabber
 * @copyright  2016 Unistra {@link http://unistra.fr}
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @author Celine Perves <cperves@unistra.fr>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once($CFG->dirroot.'/enrol/grabber/locallib.php');

$enrolid      = required_param('enrolid', PARAM_INT);
$roleid       = optional_param('roleid', -1, PARAM_INT);

$instance = $DB->get_record('enrol', array('id'=>$enrolid, 'enrol'=>'grabber'), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id'=>$instance->courseid), '*', MUST_EXIST);
$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
$canenrol = has_capability('enrol/grabber:enrol', $context);
$canunenrol = has_capability('enrol/grabber:unenrol', $context);

// Note: manage capability not used here because it is used for editing
// of existing enrolments which is not possible here.

if (!$canenrol and !$canunenrol) {
    // No need to invent new error strings here...
    require_capability('enrol/grabber:enrol', $context);
    require_capability('enrol/grabber:unenrol', $context);
}

if ($roleid < 0) {
    $roleid = $instance->roleid;
}
$roles = get_assignable_roles($context);
$roles = array('0'=>get_string('none')) + $roles;

if (!isset($roles[$roleid])) {
    // Weird - security always first!
    $roleid = 0;
}

if (!$enrol_grabber = enrol_get_plugin('grabber')) {
    throw new coding_exception('Can not instantiate enrol_grabber');
}

$instancename = $enrol_grabber->get_instance_name($instance);

$PAGE->set_url('/enrol/grabber/manage.php', array('enrolid'=>$instance->id));
$PAGE->set_pagelayout('admin');
$PAGE->set_title($enrol_grabber->get_instance_name($instance));
$PAGE->set_heading($course->fullname);
navigation_node::override_active_url(new moodle_url('/enrol/users.php', array('id'=>$course->id)));

// Create the user selector objects.
$options = array('enrolid' => $enrolid, 'accesscontext' => $context);

$potentialuserselector = new enrol_grabber_potential_participant('addselect', $options);
$currentuserselector = new enrol_grabber_current_participant('removeselect', $options);

// Build the list of options for the enrolment period dropdown.
$unlimitedperiod = get_string('unlimited');
$periodmenu = array();
for ($i=1; $i<=365; $i++) {
    $seconds = $i * 86400;
    $periodmenu[$seconds] = get_string('numdays', '', $i);
}

// Build the list of options for the starting from dropdown.

// Process add and removes.
if ($canenrol && optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $userstoassign = $potentialuserselector->get_selected_users();
    if (!empty($userstoassign)) {
        foreach($userstoassign as $adduser) {
        	$enrol_grabber->enrol_user($instance, $adduser->id, $roleid);
        }

        $potentialuserselector->invalidate_selected_users();
        $currentuserselector->invalidate_selected_users();

        //TODO: log
    }
}

// Process incoming role unassignments.
if ($canunenrol && optional_param('remove', false, PARAM_BOOL) && confirm_sesskey()) {
    $userstounassign = $currentuserselector->get_selected_users();
    if (!empty($userstounassign)) {
        foreach($userstounassign as $removeuser) {
            $enrol_grabber->unenrol_user($instance, $removeuser->id);
        }

        $potentialuserselector->invalidate_selected_users();
        $currentuserselector->invalidate_selected_users();

        //TODO: log
    }
}


echo $OUTPUT->header();
echo $OUTPUT->heading($instancename);

$addenabled = $canenrol ? '' : 'disabled="disabled"';
$removeenabled = $canunenrol ? '' : 'disabled="disabled"';

?>
<form id="assignform" method="post" action="<?php echo $PAGE->url ?>"><div>
  <input type="hidden" name="sesskey" value="<?php echo sesskey() ?>" />

  <table summary="" class="roleassigntable generaltable generalbox boxaligncenter" cellspacing="0">
    <tr>
      <td id="existingcell">
          <p><label for="removeselect"><?php print_string('enrolledusers', 'enrol'); ?></label></p>
          <?php $currentuserselector->display() ?>
      </td>
      <td id="buttonscell">
          <div id="addcontrols">
              <input name="add" <?php echo $addenabled; ?> id="add" type="submit" value="<?php echo $OUTPUT->larrow().'&nbsp;'.get_string('add'); ?>" title="<?php print_string('add'); ?>" /><br />

              <div class="enroloptions">

              <p><label for="menuroleid"><?php print_string('assignrole', 'enrol_grabber') ?></label><br />
              <?php echo html_writer::select($roles, 'roleid', $roleid, false); ?></p>

              //TODO add new parameters
              </div>
          </div>

          <div id="removecontrols">
              <input name="remove" id="remove" <?php echo $removeenabled; ?> type="submit" value="<?php echo get_string('remove').'&nbsp;'.$OUTPUT->rarrow(); ?>" title="<?php print_string('remove'); ?>" />
          </div>
      </td>
      <td id="potentialcell">
          <p><label for="addselect"><?php print_string('enrolcandidates', 'enrol'); ?></label></p>
          <?php $potentialuserselector->display() ?>
      </td>
    </tr>
  </table>
</div></form>
<?php


echo $OUTPUT->footer();
