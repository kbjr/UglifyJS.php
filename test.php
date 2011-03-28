<?php

require 'parse-js.php';

$contents = file_get_contents('test.js');

$parsed = ParseJS::parse($content);

var_dump($parsed);

?>
