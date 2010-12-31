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
| @requires   PHP >= 5.2.0
|
*/

/**
 * Path constants
 */
define('UGLIFYJS_BASEPATH', dirname(__FILE__).'/');
define('UGLIFYJS_LIBPATH', UGLIFYJS_BASEPATH.'lib/');

/**
 * The name to give the shortcur function (FALSE for none).
 */
define('UGLIFYJS_FUNCTION', 'UJS');

/**
 * The core class
 */
class UglifyJS {
	
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
	 * Sets configuration options
	 *
	 * @access  public
	 * @param   string    the option
	 * @param   mixed     the value
	 * @return  self
	 */
	public function set_option($option, $value) {
		$this->options[$option] = $value;
		return $this;
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
			'return $inst';
		'}'
	)));
}

/* End of file uglify-js.php */
