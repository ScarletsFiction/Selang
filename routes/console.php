<?php

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This is where you can register console command. You can also use 
| 'help' command to view all command list
|
*/
use \Scarlets\Console;
use \App\Library\Selang\Loader;

Console::command('selang {0}', function($rootPath){
	$root = pathinfo(realpath($rootPath));

	// The namespace root path
	Loader::$root = &$root['dirname'];

	echo Console::style("<yellow>Transpiling</yellow>");
	$path = Loader::parse("$root[filename].$root[extension]");

	echo Console::style("\n<yellow>Compiling</yellow>");
	Loader::compile("name");

	$path = Loader::$root.'/name.exe';

	echo Console::style("\n<green>Executing:</green> $path\n\n");
	passthru($path);
}, 'Transpile PHP script into Golang');