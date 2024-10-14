<?php
require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/local/activitycompletiongrade/lib.php');

$courseid = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);

require_login($course);
$context = context_course::instance($course->id);
require_capability('moodle/course:manageactivities', $context);

$PAGE->set_url(new moodle_url('/local/activitycompletiongrade/manage_bonus_points.php', array('id' => $course->id)));
$PAGE->set_title(get_string('managebonuspoints', 'local_activitycompletiongrade'));
$PAGE->set_heading($course->fullname);

if (data_submitted() && confirm_sesskey()) {
    $cmids = required_param_array('cmid', PARAM_INT);
    $bonuspoints = required_param_array('bonuspoints', PARAM_INT);
    $isbonusarray = required_param_array('isbonus', PARAM_INT);
    
    foreach ($cmids as $index => $cmid) {
        $points = $bonuspoints[$index];
        $isbonus = $isbonusarray[$index];
        if (!local_activitycompletiongrade_set_bonus_points($cmid, $points, $isbonus)) {
            \core\notification::error(get_string('errorupdatingpoints', 'local_activitycompletiongrade'));
        }
    }
    
    redirect($PAGE->url, get_string('changessaved'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managebonuspoints', 'local_activitycompletiongrade'));

$modinfo = get_fast_modinfo($course);
$modules = $modinfo->get_cms();

$table = new html_table();
$table->head = array(
    get_string('activity'),
    get_string('bonuspoints', 'local_activitycompletiongrade'),
    get_string('pointstype', 'local_activitycompletiongrade')
);
$table->data = array();

echo '<form method="post" action="'.$PAGE->url.'">';
echo '<input type="hidden" name="sesskey" value="'.sesskey().'">';

foreach ($modules as $cm) {
    if ($cm->completion != COMPLETION_TRACKING_NONE) {
        $bonuspoints = local_activitycompletiongrade_get_bonus_points($cm->id);
        $isbonus = local_activitycompletiongrade_is_bonus($cm->id);
        $table->data[] = array(
            $cm->get_formatted_name(),
            '<input type="number" name="bonuspoints[]" value="'.$bonuspoints.'" min="0">
             <input type="hidden" name="cmid[]" value="'.$cm->id.'">',
            '<select name="isbonus[]">
                <option value="1" '.($isbonus ? 'selected' : '').'>'.get_string('bonuspoints', 'local_activitycompletiongrade').'</option>
                <option value="0" '.(!$isbonus ? 'selected' : '').'>'.get_string('regularpoints', 'local_activitycompletiongrade').'</option>
             </select>'
        );
    }
}

echo html_writer::table($table);
echo '<input type="submit" value="'.get_string('savechanges').'">';
echo '</form>';

echo $OUTPUT->footer();