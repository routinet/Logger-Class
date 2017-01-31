<?php
/*
  An example of how to implement the Logger class as a global class inside a larger project.  
*/

require_once 'Logger.php';
function logit($msg, $level = LOG_LEVEL_DEBUG) {
    static $log = NULL;
    static $default_log_level = LOG_LEVEL_DEBUG;
    if (!$log) {
        $log = Logger::getInstance($default_log_level);
    }
    $log->log($msg, $level);
}
function logtrace() {
    logit(var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),1));
}

