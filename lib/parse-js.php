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
|----------------------------------------------------------
|
| This file contains the tokenizer/parser.
|
*/

/**
 * The tokenizer/parser class
 */
class UglifyJS_parser {
	
	/**
	 * JavaScript Keywords
	 *
	 * @access  protected
	 * @type    array
	 */
	protected $keywords = array(
		'break',
		'case',
		'catch',
		'const',
		'continue',
		'default',
		'delete',
		'do',
		'else',
		'finally',
		'for',
		'function',
		'if',
		'in',
		'instanceof',
		'new',
		'return',
		'switch',
		'throw',
		'try',
		'typeof',
		'var',
		'void',
		'while',
		'with'
	);
	
	/**
	 * Reserved Words
	 *
	 * @access  protected
	 * @type    array
	 */
	protected $reserved_words = array(
		'abstract',
		'boolean',
		'byte',
		'char',
		'class',
		'debugger',
		'double',
		'enum',
		'export',
		'extends',
		'final',
		'float',
		'goto',
		'implements',
		'import',
		'int',
		'interface',
		'long',
		'native',
		'package',
		'private',
		'protected',
		'public',
		'short',
		'static',
		'super',
		'synchronized',
		'throws',
		'transient',
		'volatile'
	);
	
	/**
	 * Keywords Before Expression
	 *
	 * @access  protected
	 * @type    array
	 */
	protected $keywords_before_expression = array(
		'return',
		'new',
		'delete',
		'throw',
		'else'
	);
	
	/**
	 * Keywords ATOM
	 *
	 * @access  protected
	 * @type    array
	 */
	protected $keywords_atom = array(
		'false',
		'null',
		'true',
		'undefined'
	);
	
	/**
	 * Operator Characters
	 *
	 * @access  protected
	 * @type    array
	 */
	protected $operator_chars = array(
		'+', '-', '*', '&', '%', '=', '<',
		'>', '!', '?', '|', '~', '^'
	);
	
	/**
	 * Hexadecimal Number Regular Expression
	 *
	 * @access  protected
	 * @type    string
	 */
	protected $hex_number = '/^0x[0-9a-f]+$/i';
	
	/**
	 * Octal Number Regular Expression
	 *
	 * @access  protected
	 * @type    string
	 */
	protected $oct_number = '/^0[0-7]+$/';
	
	/**
	 * Decimal Number Regular Expression
	 *
	 * @access  protected
	 * @type    string
	 */
	protected $dec_number = '/^\d*\.?\d*(?:e[+-]?\d*(?:\d\.?|\.?\d)\d*)?$/i';
	
	/**
	 * Operators
	 *
	 * @access  protected
	 * @type    array
	 */
	protected $operators = array(
		'in',
		'instanceof',
		'typeof',
		'new',
		'void',
		'delete',
		'++',
		'--',
		'+',
		'-',
		'!',
		'~',
		'&',
		'|',
		'^',
		'*',
		'/',
		'%',
		'>>',
		'<<',
		'>>>',
		'<',
		'>',
		'<=',
		'>=',
		'==',
		'===',
		'!=',
		'!==',
		'?',
		'=',
		'+=',
		'-=',
		'/=',
		'*=',
		'%=',
		'>>=',
		'<<=',
		'>>>=',
		'~=',
		'%=',
		'|=',
		'^=',
		'&=',
		'&&',
		'||'
	);
	
	/**
	 * Whitespace Characters
	 *
	 * @access  protected
	 * @type    array
	 */
	protected $whitespace_chars = array( ' ', "\n", "\r", "\t" );
	
	/**
	 * Puncuation Before Expressions
	 *
	 * @access  protected
	 * @type    array
	 */
	protected $punc_before_expression = array( '[', '{', '}', '(', ',', '.', ';', ':' );
	
	/**
	 * Puncuation Characters
	 *
	 * @access  protected
	 * @type    array
	 */
	protected $punc_chars = array( '[', ']', '{', '}', '(', ')', ',', ';', ':' );
	
	/**
	 * Regular Expression Modifiers
	 *
	 * @access  protected
	 * @type    array
	 */
	protected $regexp_modifiers = array( 'g', 'i', 'm', 's', 'y' );
	
/*
|----------------------------------------------------------------------------------------
|                              END OF TOKEN DEFINITIONS
|----------------------------------------------------------------------------------------
*/
	
	/**
	 * Check if a character is alpha-numeric
	 *
	 * @access  protected
	 * @param   string    the character to test
	 * @return  bool
	 */
	protected function is_alphanumeric_char($ch) {
		$ord = ord($ch);
		return (
			($ord >= 48 && $ord <= 57) ||
			($ord >= 65 && $ord <= 90) ||
			($ord >= 97 && $ord <= 122)
		);
	}
	
	/**
	 * Check if a character is an identifier character
	 *
	 * @access  protected
	 * @param   string    the character to test
	 * @return  bool
	 */
	protected function is_identifier_char($ch) {
		return ($this->is_alphanumeric_char($ch) || $ch == '$' || $ch == '_');
	}
	
	/**
	 * Check if a character is a digit
	 *
	 * @access  protected
	 * @param   string    the character to test
	 * @return  bool
	 */
	protected function is_digit($ch) {
		$ord = ord($ch);
		return ($ord >= 48 && $ord <= 57);
	}
	
	/**
	 * Parse a number string into a number
	 *
	 * @access  protected
	 * @param   string    the hex string
	 * @return  mixed
	 */
	protected function parse_js_number($num) {
		if (preg_match($this->hex_number, $num)) {
			return hexdec($num);
		} elseif (preg_match($this->oct_number, $num)) {
			return octdec($num);
		} elseif (preg_match($this->dec_number, $num)) {
			return ((float) $num);
		}
	}
	
	
	
	
	
	
	
	
	
	
	
	
}






















/* End of file parse-js.php */
