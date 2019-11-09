<?php
namespace App\Library\Selang;
use PhpParser\Error;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use PhpParser\ParserFactory;
use \App\Library\Selang\Loader as Lo;

// Parser for single file
class Parser{
	// File info
	public $info;

	// Script that not wrapped in a named function
	public $wild = ''; 

	// Function collection
	public $funcs = [];

	public $import = [];
	public $use = [];
	public $alias = [];
	public $structs = [];

	public function __construct($filePath, &$stmts){
		$this->info = pathinfo($filePath);

		$this->wild = $this->stmts($stmts);
	}

	public function stmts(&$ast, &$declared = [], &$other = false){
		$scripts = [];
		foreach ($ast as &$val) {
			$script = '';

			// Go: Import
			if($val instanceof Stmt\GroupUse){
				$path = implode('/', $val->prefix->parts).'/';

				foreach ($val->uses as &$uses) {
					$alias = false;
					if($uses->alias !== null)
						$this->alias[$uses->alias->name] = end($uses->name->parts);
					else
						$this->use[] = end($uses->name->parts);

					$this->import[] = implode('/', $uses->name->parts);
				}
			}

			// Go: Import
			elseif($val instanceof Stmt\Use_){
				$alias = false;
				$val = $val->uses[0];

				if($val->alias !== null)
					$this->alias[$val->alias->name] = end($val->name->parts);
				else
					$this->use[] = end($val->name->parts);

				$this->import[] = implode('/', $val->name->parts);
			}

			// Go: Expression
			elseif($val instanceof Stmt\Expression){
				$script .= $this->expr($val->expr, $declared);
			}

			// Go: Function
			if($val instanceof Stmt\Function_){
				$other = [];
				$temp = ['params'=>[]];

				if(count($val->params) === 0)
					$declared2 = &$declared;
				else
					$declared2 = $declared;

				foreach ($val->params as &$param) {
					$type = $param->type->parts ?? [$param->type->name];
					$typeName = array_pop($type);

					if(count($type) !== 0){
						$type = implode('/', $type);

						if(!isset($this->alias[$type]) && !in_array($type, $this->import))
							$this->import[] = $type;

						if(isset($this->alias[$type]))
							$type = $this->alias[$type];

						$typeName = $type.'/'.$typeName;
					}
					elseif(isset($this->alias[$typeName]))
						$typeName = $this->alias[$typeName];

					$typeName = str_replace('/', '.', $typeName);

					$temp['params'][] = [
						'name'=>$param->var->name,
						'byRef'=>$param->byRef,
						'spreads'=>$param->variadic,
						'type'=>$typeName,
						'default'=>$param->default->value ?? null,
					];

					$declared2[$param->var->name] = $typeName;
				}

				$temp['body'] = $this->stmts($val->stmts, $declared2, $other);

				$type = $other['type'];
				if($type === 'text')
					$type = 'string';

				$temp['type'] = $type;
				$this->funcs[$val->name->name] = $temp;

				// print_r($this->funcs);exit;
			}

			// Go: Return
			if($other !== false && $val instanceof Stmt\Return_){
				$other['type'] = $this->getTypeData($val->expr, $declared);
				$script .= $this->expr($val->expr, $declared, $other['type']);
				// Lo::dump($val);
			}

			// Lo::dump($val);
			if(strlen($script) !== 0)
				$scripts[] = $script;
		}

		return $scripts;
	}

	public function expr(&$stm, &$declared, &$mainType = null){
		$script = '';
		// Lo::dump($stm);

		if($stm instanceof Expr\Assign)
			$mainType = false;

		if($stm instanceof Expr\StaticCall || $stm instanceof Expr\MethodCall){
			if(isset($stm->class)){
				$ref = $stm->class->parts;
				$class = end($ref);
				$ref = implode('/', $ref);

				if(!isset($this->alias[$ref]) && !in_array($ref, $this->import))
					$this->import[] = $ref;

				if(isset($this->alias[$class]))
					$class = $this->alias[$class];
			}
			else $class = $stm->var->name;

			$args = [];
			foreach ($stm->args as &$val) {
				if($val->value instanceof Expr\Closure){
					die("Closure havent finished");
				}

				elseif($val->value instanceof Expr\Variable)
					$args[] = $val->value->value->name;

				elseif($val->value instanceof Scalar\String_)
					$args[] = '"'.$val->value->value.'"';

				elseif($val->value instanceof Scalar\LNumber)
					$args[] = $val->value->value;

				elseif($val->value instanceof Scalar\Encapsed){
					$parts = [];
					foreach ($val->value->parts as &$part) {
						if(isset($part->name))
							$parts[] = $part->name;
						else $parts[] = '"'.$part->value.'"';
					}
					$args[] = implode('+', $parts);
				}

				else Lo::dump($val, "Type undefined");
			}

			$script .= strtolower($class).'.';
			$script .= ucwords($stm->name->name);
			$script .= '('.implode(',', $args).')';
		}
		if($mainType === null)
			$mainType = $this->getTypeData($stm, $declared);

		if(isset($stm->left))
			$script .= $this->expr($stm->left, $declared, $mainType).'+';

		if(isset($stm->right))
			$script .= $this->expr($stm->right, $declared, $mainType);

		// Parse Value
		if(isset($stm->expr))
			$script .= $this->expr($stm->expr, $declared, $mainType);


		if(isset($stm->value)){
			if($stm instanceof Scalar\String_)
				$script .= "\"$stm->value\"";
			elseif($stm instanceof Scalar\LNumber)
				$script .= $stm->value;
			else Lo::dump($stm, "Value not recognized");
		}

		elseif($stm instanceof Scalar\Encapsed){
			$parts = [];
			foreach ($stm->parts as &$part) {
				if(isset($part->name))
					$parts[] = $part->name;
				else $parts[] = '"'.$part->value.'"';
			}
			$script .= implode('+', $parts);
		}

		// Go: Variable Assign
		if($stm instanceof Expr\Assign){
			if(isset($stm->expr) && $stm->expr instanceof Expr\Cast\Object_){
				$temp = [];
				foreach ($stm->expr->expr->items as &$val) {
					$temp[$val->key->value] = &$val->value->value;
				}

				$this->structs[$stm->var->name] = &$temp;
			}
			elseif(!isset($declared[$stm->var->name])){
				$declared[$stm->var->name] = $this->getTypeData($stm->expr, $declared);
				$script = $stm->var->name.' := '.$script;
			}
			else {
				$script = $stm->var->name.' = '.$script;	
			}
		}

		// Lo::dump($stm);
		return $script;
	}

	public function getTypeData($stm, &$declared){
		if($stm)

		// Deep on the left
		while(isset($stm->left))
			$stm = $stm->left;

		if($stm instanceof Scalar\String_)
			return 'string';

		if($stm instanceof Scalar\LNumber)
			return 'number';

		if($stm instanceof Expr\StaticCall || $stm instanceof Expr\MethodCall){
			// echo "Can't decide the function's returned data type\n";
			return 'string';
		}

		if(isset($stm->name))
			return $declared[$stm->name];

		Lo::dump($stm, "Failed to get data type");
	}
}