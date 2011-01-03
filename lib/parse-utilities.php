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
|----------------------------------------------------------
|
| This file contains the utilities for the tokenizer/parser.
| See the file ./lib/parse-js.php for documentation.
|
*/

/**
 * For emulating the functionality of the curry function
 */
class UglifyJS_callable {
	protected $func = null;
	protected $args = null;
	public function __construct($func, $args) {
		$this->func = $func;
		$this->args = $args;
	}
	public function __invoke() {
		$args = array_merge($this->args, func_get_args());
		return call_user_func_array($this->func, $args);
	}
}

class UglifyJS_utilities {
	
	/**
	 * Create a callable. Very similar to PHPs Closure object.
	 *
	 * @access  public
	 * @param   callback  the function
	 * @param   array     parameters to always be supplied
	 * @return  object (callable)
	 */
	public function curry($func, $args) {
		return new UglifyJS_callable($func, $args);
	}
	
	/**
	 * Run any passed callbacks
	 *
	 * @access  public
	 * @param   callback  the function that will return
	 * @return  mixed
	 */
	public function prog1($ret) {
		if (is_callable($ret)) {
			$ret = $ret();
		}
		$args = array_slice(func_get_args(), 1);
		foreach ($args as $arg) $arg();
		return $ret;
	}
	
	/**
	 * Check for a specific type/value token
	 *
	 * @access  public
	 * @param   object    the token
	 * @param   string    the token type
	 * @param   string    the value
	 * @return  bool
	 */
	public function is_token($token, $type, $value = null) {
		return ($token->type == $type && ($value === null || $token->value == $value));
	}
	
}

/**
 * Create the shortcut function
 */
if (! function_exists('UJSUtil')) {
	function &UJSUtil() {
		static $inst;
		if (! $inst) {
			$inst = new UglifyJS_utilities();
		}
		return $inst;
	}
}

/* End of file parse-utilities.php */
