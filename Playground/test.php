<?php
// use \App\User;

use Strconv, Fmt;
use Strings as Str;

$lo = 'lobak';
$lo = 'lo';
$test1 = Str::toUpper("h") + "en$lo";
$test2 = Str::toLower("Se") + Strconv::itoa(1) + 'ang';

Fmt::printf("$test1 from $test2");