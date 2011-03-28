<?php

class UglifyJS {

	protected static $options = array(
		'ast'               => false,
		'mangle'            => true,
		'mangle_toplevel'   => false,
		'squeeze'           => true,
		'make_seqs'         => true,
		'dead_code'         => true,
		'verbose'           => false,
		'show_copyright'    => true,
		'out_same_file'     => false,
		'max_line_length'   => 32768, // 32 x 1024
		'unsafe'            => false,
		'reserved_names'    => null,
		'codegen_options'   => array(
			'ascii_only'    => false,
			'beautify'      => false,
			'indent_level'  => 4,
			'indent_start'  => 0,
			'quote_keys'    => false,
			'space_colon'   => false
		),
		'output'            => true
    );
    
	public static function run($args) {
		foreach ($args as $arg) {
		
		}
	}
	
}

// ----------------------------------------------------------------------------
//  If not running as an include, auto-execute

if (! debug_backtrace()) {
	$args = ($argv) ? $argv : $_SERVER['argv'];
	array_shift($args);
	UglifyJS::run($args);
}

/* End of file uglifyjs.php */
