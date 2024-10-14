<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) { // Ensure we have permission to add site-wide settings
    $settings = new admin_settingpage('local_activitycompletiongrade', get_string('pluginname', 'local_activitycompletiongrade'));

    // Enable/disable the plugin
    $settings->add(new admin_setting_configcheckbox(
        'local_activitycompletiongrade/enabled',
        get_string('enabled', 'local_activitycompletiongrade'),
        get_string('enableddesc', 'local_activitycompletiongrade'),
        0
    ));

    // Default bonus points
    $settings->add(new admin_setting_configtext(
        'local_activitycompletiongrade/defaultpoints',
        get_string('defaultpoints', 'local_activitycompletiongrade'),
        get_string('defaultpointsdesc', 'local_activitycompletiongrade'),
        1,
        PARAM_INT
    ));

    // Which activity types to apply bonus points to
    $activities = get_module_types_names();
    $settings->add(new admin_setting_configmulticheckbox(
        'local_activitycompletiongrade/enabledactivities',
        get_string('enabledactivities', 'local_activitycompletiongrade'),
        get_string('enabledactivitiesdesc', 'local_activitycompletiongrade'),
        array_fill_keys(array_keys($activities), 1),
        $activities
    ));

    $ADMIN->add('localplugins', $settings);
}