<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Add elements for setting custom completion rules.
 *
 * @param MoodleQuickForm $mform The form object.
 * @param cm_info $cm The course module info instance.
 */
function local_activitycompletiongrade_completion_form_definition(MoodleQuickForm $mform, cm_info $cm) {
    $mform->addElement('checkbox', 'completion_bonuspoints_enabled', '', get_string('bonuspoints', 'local_activitycompletiongrade'));
    $mform->addElement('text', 'completion_bonuspoints_value', get_string('bonuspointsvalue', 'local_activitycompletiongrade'));
    $mform->setType('completion_bonuspoints_value', PARAM_INT);
    $mform->disabledIf('completion_bonuspoints_value', 'completion_bonuspoints_enabled', 'notchecked');
    $mform->addHelpButton('completion_bonuspoints_value', 'bonuspointshelp', 'local_activitycompletiongrade');
}

/**
 * Check if custom completion rule is enabled.
 *
 * @param cm_info $cm The course module info instance.
 * @param array $data The submitted form data.
 * @return bool True if any custom completion rule is enabled, false otherwise.
 */
function local_activitycompletiongrade_completion_rule_enabled(cm_info $cm, array $data): bool {
    return !empty($data['completion_bonuspoints_enabled']) && !empty($data['completion_bonuspoints_value']);
}