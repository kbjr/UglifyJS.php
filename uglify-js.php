<?php

/*
|----------------------------------------------------------
| UglifyJS.php
|----------------------------------------------------------
|
| A port of the UglifyJS JavaScript obfuscator in PHP.
|
| @author     James Brumond
| @version    0.1.1-a
| @copyright  Copyright 2010 James Brumond
| @license    Dual licensed under MIT and GPL
| @requires   PHP >= 5.3.0
|
*/

/**
 * Check PHP's version for compatability
 */
define('UGLIFYJS_PHP_VERSION', '5.3.0');
if (version_compare(PHP_VERSION, UGLIFYJS_PHP_VERSION, '<')) {
	die('UglifyJS.php requires at least PHP '.UGLIFYJS_PHP_VERSION.', you are running '.PHP_VERSION);
}

/**
 * Path constants
 */
define('UGLIFYJS_BASEPATH', dirname(__FILE__).'/');
define('UGLIFYJS_LIBPATH', UGLIFYJS_BASEPATH.'lib/');

/**
 * The name to give the shortcut function (FALSE for none).
 */
define('UGLIFYJS_FUNCTION', 'UJS');

/**
 * The currently running version of UglifyJS
 */
define('UGLIFYJS_VERSION', '0.1.1-a');

/**
 * Output internal errors (for debug only).
 * Setting this to false will cause errors to be ignored silently.
 */
define('UGLIFYJS_INTERNAL_ERRORS', true);

/**
 * The core class
 */
class UglifyJS {
	
	/**
	 * The tokenizer
	 *
	 * @access  protected
	 * @type    UglifyJS_tokenizer
	 */
	protected $tokenizer = null;
	
	/**
	 * Path constants
	 */
	const BASEPATH = UGLIFYJS_BASEPATH;
	const LIBPATH  = UGLIFYJS_LIBPATH;
	
	/**
	 * Default settings
	 */
	protected $options = array(
		'ast' => false,
		'mangle' => true,
		'mangle_toplevel' => false,
		'squeeze' => true,
		'make_seqs' => true,
		'dead_code' => true,
		'beautify' => false,
		'verbose' => false,
		'show_copyright' => true,
		'out_same_file' => false,
		'extra' => false,
		'unsafe' => false,
		'beautify_options' => array(
			'indent_level' => 4,
			'indent_start' => 0,
			'quote_keys' => false,
			'space_colon' => false
		)
	);
	
	/**
	 * The user defined exception handler
	 *
	 * @access  protected
	 * @type    callback
	 */
	protected $exception_handler = null;
	
	/**
	 * The Constructor
	 *
	 * @access  public
	 * @return  void
	 */
	public function __construct() {
		require_once(UGLIFYJS_LIBPATH.'parse-js.php');
		// Set the default error handler
		$this->exception_handler = function($ex) { throw $ex; };
	}
	
	/**
	 * Sets configuration options
	 *
	 * @access  public
	 * @param   string    the option
	 * @param   mixed     the value
	 * @return  self
	 */
	public function set_option($option, $value) {
		$option = explode('.', $option);
		if (count($option) == 2) {
			$this->options[$option[0]][$option[1]] = $value;
		} else {
			$this->options[$option[0]] = $value;
		}
		return $this;
	}
	 
	/**
	 * Sets configuration options in batch
	 *
	 * @access  public
	 * @param   array     the config options
	 * @return  self
	 */
	public function set_options($options) {
		$this->options = array_merge_recursive($this->options, $options);
		return $this;
	}
	
	/**
	 * Parses a string
	 *
	 * @access  public
	 * @param   string    the input code
	 * @param   mixed     output location
	 * @return  self
	 */
	public function parse_string($input, &$output) {
		$output = $this->parse_code_block($input);
		return $this;
	}	
	
	/**
	 * Parses a file
	 * 
	 * @access  public
	 * @param   string    the input file
	 * @param   string    output location
	 * @return  self
	 */
	public function parse_file($input, &$output) {
		$this->parse_string(file_get_contents($input), $output);
		return $this;
	}
	
	/**
	 * Sets the parse error handler
	 *
	 * @access  public
	 * @param   callback  the handler
	 * @return  bool
	 */
	public function set_exception_handler($callback) {
		if (is_callable($callback)) {
			$this->exception_handler = $callback;
			return true;
		} else { return false; }
	}
	
	/**
	 * Handles the show_copyright option
	 *
	 * @access  protected
	 * @param   array     the initial comments
	 * @return  string
	 */
	protected function show_copyright($comments) {
		$ret = '';
		foreach ($comments as $i => $comment) {
			if ($comment->type == 'comment1') {
				$ret .= '//'.$comment->value."\n";
			} else {
				$ret .= '/*'.$comment->value.'*/';
			}
		}
		return $ret;
	}
	
	/**
	 * Parse a block of code and return
	 *
	 * @access  protected
	 * @param   string    the code to parse
	 * @return  string
	 */
	protected function parse_code_block($code) {
		$result = '';
		// Set the error handler
		set_error_handler(function($errno, $msg, $file, $line, $context) {
			if (UGLIFYJS_INTERNAL_ERRORS) {
				echo 'UglifyJS Internal Error: '.$msg.' occured in '.$file.' on line '.$line.".\nContext:\n";
				print_r($context);
				die();
			}
			return true;
		});
		// Handle the show_copyright option
		if ($this->options['show_copyright']) {
			$initial_comments = array();
			$tokenizer = new UglifyJS_tokenizer($code, false);
			$comment = $tokenizer->next_token();
			$prev = null;
			while (strpos($comment->type, 'comment') === 0 && (! $prev || $prev == $comment->type)) {
				$initial_comments[] = $comment;
				$prev = $comment->type;
				$comment = $tokenizer->next_token();
			}
			$result .= $this->show_copyright($initial_comments);
		}
		// Start parsing
		try {
			$this->start_timer('parse');
			$ast = new UglifyJS_parser($code);
			$ast = $ast->run_parser();
			$parse_time = $this->read_timer('parse');
			// Reset the error handler
			restore_error_handler();
			return $result;
		// Handle parse errors
		} catch (UglifyJS_parse_error $ex) {
			$this->exception_handler($ex);
		// Handle other exceptions
		} catch (Exception $ex) {
			$this->exception_handler($ex);
		}
		// Reset the error handler
		restore_error_handler();
	}
	
	/**
	 * Benchmarking timers
	 *
	 * @access  protected
	 * @type    array
	 */
	protected $timers = array();
	
	/**
	 * Create/start a benchmarker
	 *
	 * @access  protected
	 * @param   string    the name
	 * @return  void
	 */
	protected function start_timer($name) {
		$this->timers[$name] = new UglifyJS_benchmarker(true);
		$this->timers[$name]->start();
	}
	
	/**
	 * Gets the time from a benchmarker
	 *
	 * @access  protected
	 * @return  float
	 */
	protected function read_timer($name) {
		if (isset($this->timers[$name])) {
			return $this->timers[$name]->get_time();
		}
	}
	
}

/**
 * Handles benchmarking
 */
class UglifyJS_benchmarker {
	protected $start = null;
	protected $float = null;
	public function __construct($float = false) {
		$this->float =!! $float;
	}
	public function start() {
		$this->start = microtime($this->float);
	}
	public function get_time() {
		$end = microtime($this->float);
		return ($end - $this->start);
	}
}

/**
 * Define the shortcut function
 */
if (UGLIFYJS_FUNCTION && ! function_exists(UGLIFYJS_FUNCTION)) {
	eval(implode("\n", array(
		'function &'.UGLIFYJS_FUNCTION.'() {',
			'static $inst;',
			'if (! $inst) {',
				'$inst = new UglifyJS();',
			'}',
			'return $inst;',
		'}'
	)));
}

/* End of file uglify-js.php */
