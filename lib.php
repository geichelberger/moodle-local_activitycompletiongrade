<?php
defined('MOODLE_INTERNAL') || die();

function local_activitycompletiongrade_get_completion_active_rule_descriptions($cm) {
    if ($cm->completion == COMPLETION_TRACKING_NONE) {
        return [];
    }

    $descriptions = [];
    $bonuspoints = local_activitycompletiongrade_get_bonus_points($cm->id);
    if ($bonuspoints > 0) {
        $descriptions[] = get_string('completionrequirementbonus', 'local_activitycompletiongrade', $bonuspoints);
    }

    return $descriptions;
}

function local_activitycompletiongrade_cm_info_dynamic(cm_info $cm) {
    if ($cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return;
    }

    $bonuspoints = local_activitycompletiongrade_get_bonus_points($cm->id);
    if ($bonuspoints > 0) {
        $cm->customdata['customcompletionrules']['bonuspoints'] = $bonuspoints;
    }
}

function local_activitycompletiongrade_get_completion_state($course, $cm, $userid, $type) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $bonuspoints = local_activitycompletiongrade_get_bonus_points($cm->id);
    if ($bonuspoints <= 0) {
        return $type;
    }

    $grades = grade_get_grades($course->id, 'mod', $cm->modname, $cm->instance, $userid);
    if (!empty($grades->items[0]->grades[$userid])) {
        $grade = $grades->items[0]->grades[$userid]->grade;
        if ($grade >= $bonuspoints) {
            return COMPLETION_COMPLETE;
        }
    }

    return COMPLETION_INCOMPLETE;
}

function local_activitycompletiongrade_course_module_completion_updated(\core\event\course_module_completion_updated $event) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (!get_config('local_activitycompletiongrade', 'enabled')) {
        return;
    }

    $data = $event->get_data();
    $cmid = $data['contextinstanceid'];
    $userid = $data['relateduserid'];
    $courseid = $data['courseid'];

    $cm = get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
    $enabledactivities = get_config('local_activitycompletiongrade', 'enabledactivities');
    $enabledactivities = !empty($enabledactivities) ? explode(',', $enabledactivities) : array();
    if (!in_array($cm->modname, $enabledactivities)) {
        return;
    }

    $completion = new completion_info(get_course($courseid));
    $current = $completion->get_data($cm, false, $userid);

    if ($current->completionstate == COMPLETION_COMPLETE || $current->completionstate == COMPLETION_COMPLETE_PASS) {
        $bonuspoints = local_activitycompletiongrade_get_bonus_points($cmid);
        if ($bonuspoints > 0) {
            local_activitycompletiongrade_award_grade($userid, $courseid, $cmid, $bonuspoints);
        }
    }
}

function local_activitycompletiongrade_get_bonus_points($cmid) {
    global $DB;
    
    $record = $DB->get_record('local_activitycompletiongrade', ['cmid' => $cmid]);
    if ($record) {
        return $record->bonuspoints;
    } else {
        return get_config('local_activitycompletiongrade', 'defaultpoints');
    }
}

function local_activitycompletiongrade_set_bonus_points($cmid, $points, $isbonus = true) {
    global $DB;
    
    $record = $DB->get_record('local_activitycompletiongrade', ['cmid' => $cmid]);
    if ($record) {
        $record->bonuspoints = $points;
        $DB->update_record('local_activitycompletiongrade', $record);
    } else {
        $record = new stdClass();
        $record->cmid = $cmid;
        $record->bonuspoints = $points;
        $DB->insert_record('local_activitycompletiongrade', $record);
    }

    // Get course id for the given cmid
    $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
    $courseid = $cm->course;

    // Update or create grade item
    local_activitycompletiongrade_grade_item_update($courseid, $cmid, $points, $isbonus);

    return true;
}

function local_activitycompletiongrade_award_bonus_points($userid, $courseid, $points) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $gradeitems = grade_item::fetch_all(array('itemtype' => 'manual', 'courseid' => $courseid, 'itemname' => 'Bonus Points'));
    if (empty($gradeitems)) {
        $gradeitem = new grade_item(array('courseid' => $courseid, 'itemtype' => 'manual', 'itemname' => 'Bonus Points'));
        $gradeitem->insert();
    } else {
        $gradeitem = reset($gradeitems);
    }

    $grade = grade_grade::fetch(array('itemid' => $gradeitem->id, 'userid' => $userid));
    if (!$grade) {
        $grade = new grade_grade();
        $grade->itemid = $gradeitem->id;
        $grade->userid = $userid;
        $grade->rawgrade = $points;
        $grade->insert();
    } else {
        $grade->rawgrade += $points;
        $grade->update();
    }

    grade_regrade_final_grades($courseid);
}

function local_activitycompletiongrade_extend_settings_navigation($settingsnav, $context) {
    global $PAGE;

    if ($PAGE->cm && has_capability('moodle/course:manageactivities', $context)) {
        $enabledactivities = get_config('local_activitycompletiongrade', 'enabledactivities');
        $enabledactivities = !empty($enabledactivities) ? explode(',', $enabledactivities) : array();
        
        if (in_array($PAGE->cm->modname, $enabledactivities)) {
            $url = new moodle_url('/local/activitycompletiongrade/set_points.php', array('id' => $PAGE->cm->id));
            $settingnode = $settingsnav->add(get_string('bonuspoints', 'local_activitycompletiongrade'), $url, navigation_node::TYPE_SETTING);
        }
    }
}

function local_activitycompletiongrade_extend_moodle_completion_api() {
    return array(
        'get_completion_active_rule_descriptions' => 'local_activitycompletiongrade_get_completion_active_rule_descriptions',
        'get_completion_state' => 'local_activitycompletiongrade_get_completion_state',
    );
}

function local_activitycompletiongrade_extend_navigation_course($navigation, $course, $context) {
    if (has_capability('moodle/course:manageactivities', $context)) {
        $url = new moodle_url('/local/activitycompletiongrade/manage_bonus_points.php', array('id' => $course->id));
        $navigation->add(
            get_string('managebonuspoints', 'local_activitycompletiongrade'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            null,
            new pix_icon('i/grades', '')
        );
    }
}

function local_activitycompletiongrade_grade_item_update($courseid, $cmid, $bonuspoints, $isbonus = true) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    $cm = get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
    
    // Get the course category
    $course_category = grade_category::fetch_course_category($courseid);

    $params = array(
        'courseid' => $courseid,
        'itemtype' => 'manual',
        'itemmodule' => 'local_activitycompletiongrade',
        'iteminstance' => $cmid,
        'itemnumber' => 0,
        'categoryid' => $course_category->id  // Set the category ID
    );

    $item = grade_item::fetch($params);
    if (!$item) {
        $item = new grade_item($params);
        $item->itemname = $cm->name . ' - ' . get_string('bonuspoints', 'local_activitycompletiongrade');
        $item->gradetype = GRADE_TYPE_VALUE;
    }

    $item->grademax = $bonuspoints;
    $item->grademin = 0;
    $item->weightoverride = $isbonus ? 0 : 1;
    $item->aggregationcoef = $isbonus ? 1 : 0;

    if ($item->id) {
        $item->update();
    } else {
        $item->insert();
    }

    return $item->id;
}

function local_activitycompletiongrade_completion_callback($cm, $userid, $isbonus = true) {
    global $DB;

    $bonuspoints = local_activitycompletiongrade_get_bonus_points($cm->id);
    $courseid = $cm->course;

    // Create or update grade item
    local_activitycompletiongrade_grade_item_update($courseid, $cm->id, $bonuspoints, $isbonus);

    // Update user's grade
    local_activitycompletiongrade_grade_item_update_grades($courseid, $cm->id, $userid, $bonuspoints);
}

function local_activitycompletiongrade_is_bonus($cmid) {
    global $DB;
    $gradeitem = $DB->get_record('grade_items', array('itemmodule' => 'activitycompletiongrade', 'iteminstance' => $cmid));
    return $gradeitem ? ($gradeitem->aggregationcoef == 1) : true; // Default to true if not found
}

function local_activitycompletiongrade_completion_observer(\core\event\course_module_completion_updated $event) {
    global $DB;

    if (!get_config('local_activitycompletiongrade', 'enabled')) {
        return;
    }

    $data = $event->get_data();
    $cmid = $data['contextinstanceid'];
    $userid = $data['relateduserid'];
    $courseid = $data['courseid'];

    $cm = get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
    $enabledactivities = get_config('local_activitycompletiongrade', 'enabledactivities');
    $enabledactivities = !empty($enabledactivities) ? explode(',', $enabledactivities) : array();
    if (!in_array($cm->modname, $enabledactivities)) {
        return;
    }

    $completion = new completion_info(get_course($courseid));
    $current = $completion->get_data($cm, false, $userid);

    if ($current->completionstate == COMPLETION_COMPLETE || $current->completionstate == COMPLETION_COMPLETE_PASS) {
        $bonuspoints = local_activitycompletiongrade_get_bonus_points($cmid);
        if ($bonuspoints > 0) {
            local_activitycompletiongrade_award_grade($userid, $courseid, $cmid, $bonuspoints);
        }
    }
}
function local_activitycompletiongrade_award_grade($userid, $courseid, $cmid, $points) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $cm = get_coursemodule_from_id('', $cmid, $courseid, false, MUST_EXIST);
    $isbonus = local_activitycompletiongrade_is_bonus($cmid);

    // Ensure grade item exists
    local_activitycompletiongrade_grade_item_update($courseid, $cmid, $points, $isbonus);

    $gradeitem = $DB->get_record('grade_items', [
        'courseid' => $courseid,
        'itemtype' => 'manual',
        'itemmodule' => 'local_activitycompletiongrade',
        'iteminstance' => $cmid
    ], '*');

    $grade = new stdClass();
    $grade->itemid = $gradeitem->id;
    $grade->userid = $userid;
    $grade->rawgrade = $points;
    $grade->finalgrade = $points;
    $grade->timecreated = time();
    $grade->timemodified = time();

    $existing = $DB->get_record('grade_grades', [
        'itemid' => $gradeitem->id,
        'userid' => $userid
    ]);

    if ($existing) {
        $grade->id = $existing->id;
        $DB->update_record('grade_grades', $grade);
    } else {
        $DB->insert_record('grade_grades', $grade);
    }

    grade_regrade_final_grades($courseid, $userid, $gradeitem);

    return GRADE_UPDATE_OK;
}

function local_activitycompletiongrade_fix_orphaned_grade_items() {
    global $DB;

    $orphaned_items = $DB->get_records_sql("
        SELECT gi.*
        FROM {grade_items} gi
        LEFT JOIN {grade_categories} gc ON gi.categoryid = gc.id
        WHERE gi.itemtype = 'manual' 
        AND gi.itemmodule = 'local_activitycompletiongrade'
        AND (gi.categoryid IS NULL OR gc.id IS NULL)
    ");

    foreach ($orphaned_items as $item) {
        $course_category = grade_category::fetch_course_category($item->courseid);
        $item->categoryid = $course_category->id;
        $DB->update_record('grade_items', $item);
    }
}
