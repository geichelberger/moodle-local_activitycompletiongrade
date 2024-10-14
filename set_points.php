<?php
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/activitycompletiongrade/lib.php');

$id = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url('/local/activitycompletiongrade/set_points.php', array('id' => $cm->id));
$PAGE->set_title(get_string('bonuspoints', 'local_activitycompletiongrade'));
$PAGE->set_heading($course->fullname);

$current_points = local_activitycompletiongrade_get_bonus_points($cm->id);
$current_isbonus = local_activitycompletiongrade_is_bonus($cm->id);

if ($data = data_submitted()) {
    require_sesskey();
    $bonuspoints = required_param('bonuspoints', PARAM_INT);
    $isbonus = optional_param('isbonus', 1, PARAM_INT);
    
    if (local_activitycompletiongrade_set_bonus_points($cm->id, $bonuspoints, $isbonus)) {
        redirect($PAGE->url, get_string('changessaved'));
    } else {
        \core\notification::error(get_string('errorupdatingpoints', 'local_activitycompletiongrade'));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('bonuspoints', 'local_activitycompletiongrade'));

// Create a simple form
$form = '<form method="post" action="'.$PAGE->url.'">';
$form .= '<input type="hidden" name="sesskey" value="'.sesskey().'">';
$form .= '<div>';
$form .= '<label for="bonuspoints">'.get_string('bonuspointsdesc', 'local_activitycompletiongrade').'</label>';
$form .= '<input type="number" name="bonuspoints" id="bonuspoints" value="'.$current_points.'">';
$form .= '</div>';
$form .= '<div>';
$form .= '<label for="isbonus">'.get_string('treataspointstype', 'local_activitycompletiongrade').'</label>';
$form .= '<select name="isbonus" id="isbonus">';
$form .= '<option value="1" '.($current_isbonus ? 'selected' : '').'>'.get_string('bonuspoints', 'local_activitycompletiongrade').'</option>';
$form .= '<option value="0" '.(!$current_isbonus ? 'selected' : '').'>'.get_string('regularpoints', 'local_activitycompletiongrade').'</option>';
$form .= '</select>';
$form .= '</div>';
$form .= '<div>';
$form .= '<input type="submit" value="'.get_string('savechanges').'">';
$form .= '</div>';
$form .= '</form>';

echo $form;
echo $OUTPUT->footer();