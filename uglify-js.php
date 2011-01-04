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
	 * The Constructor
	 *
	 * @access  public
	 * @return  void
	 */
	public function __construct() {
		require_once(UGLIFYJS_LIBPATH.'parse-js.php');
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
	 * Parse a block of code and return
	 *
	 * @access  protected
	 * @param   string    the code to parse
	 * @return  string
	 */
	protected function parse_code_block($code) {
		return $code;
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
