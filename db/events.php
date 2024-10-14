<?php
defined('MOODLE_INTERNAL') || die();

$observers = array(
    array(
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => 'local_activitycompletiongrade_course_module_completion_updated',
    ),
);

$observers = array(
    array(
        'eventname' => '\core\event\course_module_completion_updated',
        'callback' => 'local_activitycompletiongrade_completion_observer',
        'includefile' => '/local/activitycompletiongrade/lib.php',

    ),
);

