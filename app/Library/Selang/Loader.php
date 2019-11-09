<?php
namespace App\Library\Selang;
use PhpParser\Error;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\NodeDumper;
use PhpParser\ParserFactory;

Loader::$dumper = new NodeDumper;
Loader::$parser = (new ParserFactory)->create(ParserFactory::ONLY_PHP7);

// The loader will parse the file
// And try to parse the another file nearby (As a current namespace)
class Loader{
	public static $root;
	public static $dumper;
	public static $parser;

	// Dump and die
	public static function dump(&$stm, $msg = false){
		die(self::$dumper->dump($stm).($msg ? "\n\n$msg" : ''));
	}

	// Dump as JSON and die
	public static function dd(&$obj){
		die(json_encode($obj, 128));
	}

	public static function parse($file, $nearby = true){
		$info = pathinfo(self::$root."/$file");

		$code = file_get_contents(self::$root."/$file");
		$ast = self::$parser->parse($code);

		$namespace = [
			'path'=>[],
			'name'=>''
		];

		// Go: Package
		if($ast[0] instanceof Stmt\Namespace_){
			$namespace['path'] = &$ast[0]->name->parts;
			$namespace['name'] = array_pop($namespace['path']);
			$ast = &$ast[0]->stmts;
		}
		elseif($info['dirname'] === self::$root)
			$namespace['name'] = "main";

		$file = new Parser(self::$root."/$file", $ast);
		// self::dd($file);

		$header = "package $namespace[name]\n\n";

		// Go: Import
		foreach ($file->import as &$val) {
			$val = strtolower($val);
		}

		$imports = 'import("'.implode('";"', $file->import).'")';
		$header .= "$imports\n\n";

		$content = "func main(){\n".implode("\n", $file->wild)."\n}";

		// print_r($imports);exit;
		$save = implode('/', $namespace['path'])."/$info[filename].go";
		file_put_contents("$info[dirname]/temp$save", "$header$content");

		return $save;
	}

	public static function compile($output){
		exec("cd ".self::$root."/temp/ & go build -o ../$output.exe");
	}
}