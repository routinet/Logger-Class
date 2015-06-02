<?php
namespace sbink;
/*
  Logger class
  Provides a logging interface, including tee to file/stdout and severity gradiations
*/

/* log level constants */
const LOG_LEVEL_FATAL = 0;
const LOG_LEVEL_ERROR = 1;
const LOG_LEVEL_WARN  = 2;
const LOG_LEVEL_INFO  = 3;
const LOG_LEVEL_DEBUG = 4;

/* log mode constants */
const LOG_MODE_FILE   = 0;
const LOG_MODE_STDOUT = 1;
const LOG_MODE_TEE    = 2;

class Logger {
  private static $instance = NULL;

  private   $_logfile     = NULL;
  protected $logfile      = '';
  protected $log_location = '';
  protected $log_level    = LOG_LEVEL_ERROR;
  protected $log_mode     = LOG_MODE_FILE;

  private function __construct($lvl=LOG_LEVEL_ERROR, $fn='', $loc = NULL, $mode=LOG_MODE_FILE) {
    $this->setLevel($lvl);
    $this->setMode($mode);
    $this->setLogLocation($loc,$fn);
  }

  private static function array_value($key, $array, $default_value = null) {
    return (is_array($array) && array_key_exists($key,$array)) ? $array[$key] : $default_value;
  }


  public static function getBackTrace($most_recent = false) {
    $backTrace = debug_backtrace();
    array_shift($backTrace);
    if ($most_recent) {
      return array_shift($backTrace);
    }
    $showArgs = false;
    $maxArgLen=80;
    $message = '';
    foreach ($backTrace as $idx => $trace) {
      $args = array();
      $fnName = self::array_value('function', $trace);
      $className = array_key_exists('class', $trace) ? ($trace['class'] . $trace['type']) : '';

      // do now show args for a few password related functions
      $skipArgs = ($className == 'DB::' && $fnName == 'connect') ? true : false;

      foreach ($trace['args'] as $arg) {
        if (! $showArgs || $skipArgs) {
          $args[] = '(' . gettype($arg) . ')';
          continue;
        }
        switch ($type = gettype($arg)) {
          case 'boolean':
            $args[] = $arg ? 'TRUE' : 'FALSE';
            break;
          case 'integer':
          case 'double':
            $args[] = $arg;
            break;
          case 'string':
            $args[] = '"' . (string) $arg . '"';
            break;
          case 'array':
            $args[] = '(Array:'.count($arg).')';
            break;
          case 'object':
            $args[] = 'Object(' . get_class($arg) . ')';
            break;
          case 'resource':
            $args[] = 'Resource';
            break;
          case 'NULL':
            $args[] = 'NULL';
            break;
          default:
            $args[] = "($type)";
            break;
        }
      }

      $message .= sprintf(
        "#%s %s(%s): %s%s(%s)\n",
        $idx,
        self::array_value('file', $trace, '[internal function]'),
        self::array_value('line', $trace, ''),
        $className,
        $fnName,
        implode(", ", $args)
      );
    }
    $message .= sprintf("#%s {main}\n", 1+$idx);
    return $message;
  }

  public static function getInstance($lvl=LOG_LEVEL_ERROR, $fn='', $loc = NULL, $mode=LOG_MODE_FILE) {
    if (!static::$instance) {
      static::$instance = new static($lvl, $fn, $loc, $mode);
    }
    return static::$instance;
  }

  protected function _closeFile() {
    if ($this->_logfile) {
      @fclose($this->_logfile);
    }
    $this->_logfile = NULL;
  }

  protected function _fullLogName() {
    $fn='';
    if ($this->logfile) {
      $fn = $this->log_location .
            (in_array(substr($this->log_location,-1),['/','\\']) ? '' : '/') .
            $this->logfile;
    }
    return $fn;
  }

  protected function _isUsingFile() {
    return in_array($this->log_mode,[LOG_MODE_FILE,LOG_MODE_TEE]);
  }

  protected function _isUsingStdout() {
    return in_array($this->log_mode,[LOG_MODE_STDOUT,LOG_MODE_TEE]);
  }

  protected function _openFile() {
    if ($this->_isUsingFile() && $this->logfile) {
      $fn=$this->_fullLogName();
      $this->_logfile = fopen($fn,'a');
      if (!$this->_logfile) {
        $this->_closeFile();
        $this->logfile = '';
        $this->log("Could not open file '$fn' for writing, reverting to error_log()");
      }
    }
  }

  protected function _setFile($fn) {
    $fn=(string)$fn;
    $this->logfile=$fn;
  }

  protected function _setLocation($loc) {
    $loc=(string)$loc;
    if (!$loc) { $loc=$_SERVER['DOCUMENT_ROOT']; }
    if (is_dir($loc)) {
      $this->log_location = $loc;
    }
  }

  protected function closeLogFile() {
    if ($this->_logfile) {
      @fclose($this->_logfile);
      $this->_logfile = NULL;
    }
  }

  protected function initLog() {
    if ($this->_isUsingFile() && $this->logfile) {
      $this->_openFile();
    }
  }

  public function log($msg, $lvl=LOG_LEVEL_INFO) {
    static $labels = array(
                            LOG_LEVEL_FATAL => 'FATAL',
                            LOG_LEVEL_ERROR => 'ERROR',
                            LOG_LEVEL_WARN  => 'WARN',
                            LOG_LEVEL_INFO  => 'INFO',
                            LOG_LEVEL_DEBUG => 'DEBUG',
                          );
    $lvl = (int)$lvl;
    if ($lvl < 0) { $lvl = 0; }
    if ($lvl <= $this->log_level) {
      $datestr = date('Y-m-d H:i:s');
      $lvllabel = self::array_value($lvl,$labels,'CUSTOM');
      $msg = (string)$msg;
      $logmsg =  "[$lvllabel] $msg";
      if ($this->_isUsingStdout()) {
        echo "{$datestr} {$logmsg}\n";
      }
      if ($this->_isUsingFile()) {
        if ($this->_logfile) {
          $res = fwrite($this->_logfile,"{$datestr} {$logmsg}\n");
          if ($res===false) {
            error_log("COULD NOT WRITE TO LOG FILE '".$this->_fullLogName()."'!");
            error_log($logmsg);
          }
        } else {
          error_log($logmsg);
        }
      }
    }
  }

  public function setLevel($lvl=LOG_LEVEL_ERROR) {
    $lvl = (int)$lvl;
    if ($lvl < 1) { $lvl = 0; }
    $this->log_level = $lvl;
  }

  public function setLogFile($fn) {
    if ($fn) {
      $this->closeLogFile();
      $this->logfile = $fn;
    }
    $this->initLogFile();
  }

  public function setLogLocation($loc,$fn) {
    $this->_setLocation($loc);
    $this->_setFile($fn);
    $this->initLog();
  }

  public function setMode($mode=LOG_MODE_FILE) {
    $mode = (int)$mode;
    if (!in_array($mode,[0,1,2])) { $mode = LOG_MODE_FILE; }
    $this->log_mode = $mode;
  }
}