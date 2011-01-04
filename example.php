<?php

// Override the default content-type
header('Content-Type: text/plain');

// The input file
define('INPUT_FILE', dirname(__FILE__).'/original-source/test/unit/scripts.js');

// Include the class
require_once('uglify-js.php');

// Set options
UJS()->set_options(array(
	'show_copyright' => true
));

// Do the string parsing and output
UJS()->parse_file(INPUT_FILE, $result);
echo $result;

/* End of file example.php */
