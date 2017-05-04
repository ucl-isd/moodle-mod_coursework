<?php

define('CLI_SCRIPT', true);

require_once(dirname(__FILE__).'/../../../config.php');

global $CFG;

require_once($CFG->dirroot.'/mod/coursework/lib.php');

coursework_cron();