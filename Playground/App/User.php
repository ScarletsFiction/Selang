<?php
namespace App;
use \fmt;

// Still being maintained
// Please help if you can

/**
 * Just testing for dummy class
 */
class User{
	/** @var int */
	public $id;

	function __construct($id){
		$this->id = $id;
	}

	function print($text){
		// fmt::fprintf("$text$this->id");
		echo "$text$this->id";
	}
}