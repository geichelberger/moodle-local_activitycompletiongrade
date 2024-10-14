<?php
define('CLI_SCRIPT', true);

require(__DIR__.'/../../config.php');
require_once($CFG->libdir.'/clilib.php');
require_once($CFG->dirroot.'/local/activitycompletiongrade/lib.php');

// Now call the function
local_activitycompletiongrade_fix_orphaned_grade_items();

cli_writeln("Orphaned grade items have been fixed.");