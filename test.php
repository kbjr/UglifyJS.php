<?php

header('Content-Type: text/plain');

require 'parse-js.php';

$contents = file_get_contents('test.js');

$tokenizer = ParseJS::tokenizer($contents);

var_dump($tokenizer->get_tokens());

/* End of file test.php */
