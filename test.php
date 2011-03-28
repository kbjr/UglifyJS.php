<?php

header('Content-Type: text/plain');

require 'parse-js.php';

$contents = file_get_contents('test.js');

die($contents);

$tokenizer = ParseJS::tokenizer($contents);

$parsed = $tokenizer->next_token();

var_dump($parsed);

?>
