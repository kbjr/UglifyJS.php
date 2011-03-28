<?php

/*
|------------------------------------------------
| JavaScript Parser
|------------------------------------------------
|
| A JavaScript parser ported from the UglifyJS [1] JavaScript
| tokenizer which was itself a port of parse-js [2], a JavaScript
| parser by Marijn Haverbeke.
|
| [1] https://github.com/mishoo/UglifyJS/
| [2] http://marijn.haverbeke.nl/parse-js/
|
|------------------------------------------------
|
| @author     James Brumond
| @version    0.1.1-dev
| @copyright  Copyright 2011 James Brumond
| @license    Dual licensed under MIT and GPL
|
*/

class JavaScript_Parser {

// ----------------------------------------------------------------------------
//  Properties

	protected $input        = null;
	protected $token        = null;
	protected $prev         = null;
	protected $peeked       = null;
	protected $in_function  = 0;
	protected $in_loop      = 0;
	protected $labels       = array();

	protected $exigent_mode = null;
	protected $embed_tokens = null;

// ----------------------------------------------------------------------------
//  Public functions

	public function __construct($input, $exigent_mode = false, $embed_tokens = false) {
		self::_ASSIGNMENT();
		self::_PRECEDENCE();
		if (is_string($input)) {
			$this->input = new JavaScript_Tokenizer($input);
		} else {
			$this->input = $input;
		}
		$this->token = $this->next();
		$this->exigent_mode = $exigent_mode;
		$this->embed_tokens = $embed_tokens;
	}

	public function run() {
		$a = array();
		while (! $this->is('eof')) {
			$a[] = $this->statement();
		}
		return array('toplevel', $a);
	}

// ----------------------------------------------------------------------------
//  Error handler functions

	protected function raise($msg, $line, $col, $pos) {
		throw new JS_Parse_Error($msg, $line, $col, $pos);
	}

	protected function croak($msg, $line = null, $col = null, $pos = null) {
		$ctx = $this->input->context();
		$this->raise(
			$msg,
			(($line !== null) ? $line : $ctx->line),
			(($col  !== null) ? $col  : $ctx->col),
			(($pos  !== null) ? $pos  : $ctx->pos)
		);
	}

	protected function token_error($token, $msg) {
		$this->croak($msg, $token->line, $token->col);
	}

	protected function unexpected($token = null) {
		if (! $token) {
			$token = $this->token;
		}
		$this->token_error($token, 'Unexpected token '.$token->type.' ('.$token->value.')');
	}

	protected function expect_token($type, $val = null) {
		if ($this->is($type, $val)) {
			return $this->next();
		}
		$this->token_error($this->token, 'Unexpected token '.$this->token->type.', expected '.$type);
	}

	protected function expect($punc) {
		return $this->expect_token('punc', $punc);
	}

// ----------------------------------------------------------------------------
//  Semicolon handling functions

	protected function can_insert_semicolon() {
		return (! $this->exigent_mode && (
			$this->token->nlb || $this->is('eof') || $this->is('punc', '}')
		));
	}

	protected function semicolon() {
		if ($this->is('punc', ';')) {
			$this->next();
		} elseif (! $this->can_insert_semicolon()) {
			$this->unexpected();
		}
	}

// ----------------------------------------------------------------------------
//  Internal helper functions

	protected function is($type, $value = null) {
		return ParseJS::is_token($this->token, $type, $value);
	}

	protected function next() {
		$this->prev = $this->token;
		if ($this->peeked) {
			$this->token = $this->peeked;
			$this->peeked = null;
		} else {
			$this->token = $this->input->next_token();
		}
		return $this->token;
	}

	protected function parenthesised() {
		$this->expect('(');
		$exp = $this->expression();
		$this->expect(')');
		return $exp;
	}

	protected function add_tokens($str, $start = null, $end = null) {
		if ($str instanceof NodeWithToken) {
			return $str;
		} else {
			return new NodeWithToken($str, $start, $end);
		}
	}

	protected function statement() {
		if ($this->embed_tokens) {
			$start = $this->token;
		}
		$argv = func_get_args();
		$ast = call_user_func(function() use($argv) {
			if ($this->is('operator', '/')) {
				$this->peeked = null;
				$this->token = $this->input->next_token(true);
			}
			switch ($this->token->type) {
				case 'num':
				case 'string':
				case 'regexp':
				case 'operator':
				case 'atom':
					return $this->simple_statement();
				break;
				case 'name':
					if (ParseJS::is_token($this->peek(), 'punc', ':')) {
						$this->next();
						$this->next();
						return $this->labeled_statement($this->token->value);
					} else {
						return $this->simple_statement();
					}
				break;
				case 'punc':
					switch ($this->token->value) {
						case '{':
							return array('block', $this->block_());
						break;
						case '[':
						case '(':
							return $this->simple_statement();
						break;
						case ';':
							$this->next();
							return array('block');
						break;
					}
				break;
				case 'keyword':
					$token_value = $this->token->value;
					$this->next();
					switch($token_value) {
						case 'break':
						case 'continue':
							return $this->break_cont($token_value);
						break;
						case 'debugger':
							$this->semicolon();
							return array('debugger');
						break;
					}
				break;
				case 'do':
					$body = $this->do_in_loop('statement');
					$this->expect_token('keyword', 'while');
					$paren = $this->parenthesised();
					$this->semicolon();
					return array('do', $paren, $body);
				break;
				case 'for':
					return $this->for_();
				break;
				case 'function':
					return $this->function_(true);
				break;
				case 'if':
					return $this->if_();
				break;
				case 'return':
					if (! $this->in_function) {
						$this->croak('"return" outside of function');
					}
					if ($this->is('punc', ';')) {
						$this->next();
						return array('return', null);
					} elseif ($this->can_insert_semicolon()) {
						return array('return', null);
					} else {
						$exp = $this->expression();
						$this->semicolon();
						return array('return', $exp);
					}
				break;
				case 'switch':
					return array('switch', $this->parenthesised(), $this->switch_block_());
				break;
				case 'throw':
					$exp = $this->expression();
					$this->semicolon();
					return array('throw', $exp);
				break;
				case 'try':
					return $this->try_();
				break;
				case 'var':
					$var = $this->var_();
					$this->semicolon();
					return $var;
				break;
				case 'const':
					$const = $this->const_();
					$this->semicolon();
					return $const;
				break;
				case 'while':
					return array('while', $this->parenthesised(), $this->do_in_loop('statement'));
				break;
				case 'with':
					return array('with', $this->parenthesised(), $this->statement());
				break;
				default:
					$this->unexpected();
				break;
			}
		});
		if ($this->embed_tokens) {
			$ast[0] = $this->add_tokens($ast[0], $start, $this->prev);
		}
		return $ast;
	}

	protected function labeled_statement($label) {
		$this->labels[] = $label;
		$start = $this->token;
		$stat = $this->statement();
		if ($this->exigent_mode && ! in_array($stat[0], ParseJS::$STATEMENTS_WITH_LABELS)) {
			$this->unexpected($start);
		}
		array_pop($this->labels);
		return array('label', $label, $stat);
	}

	protected function simple_statement() {
		$exp = $this->expression();
		$this->semicolon();
		return array('stat', $exp);
	}

	protected function break_cont($type) {
		$name = $this->is('name') ? $this->token->value : null;
		if ($name !== null) {
			$this->next();
			if (! in_array($name, $this->labels)) {
				$this->croak('Label "'.$name.'" without matching loop or statement');
			}
		} elseif (! $this->in_loop) {
			$this->croak($type.' not inside a loop or switch');
		}
		$this->semicolon();
		return array($type, $name);
	}

	protected function for_() {
		$this->expect('(');
		$init = null;
		if (! $this->is('punc', ';')) {
			if ($this->is('keyword', 'var')) {
				$this->next();
				$init = $this->var_(true);
			} else {
				$init = $this->expression(true, true);
			}
			if ($this->is('operator', 'in')) {
				return $this->for_in($init);
			}
		}
		return $this->regular_for($init);
	}

	protected function regular_for($init) {
		$this->expect(';');
		$test = $this->is('punc', ';') ? null : $this->expression();
		$this->expect(';');
		$step = $this->is('punc', ')') ? null : $this->expression();
		$this->expect(')');
		return array('for', $init, $test, $step, $this->do_in_loop('statement'));
	}

	protected function for_in($init) {
		$lhs = ($init[0] == 'var') ? array('name', $init[1][0]) : $init;
		$this->next();
		$obj = $this->expression();
		$this->expect(')');
		return array('for-in', $init, $lhs, $obj, $this->do_in_loop('statement'));
	}

	protected function function_($in_statement = null) {
		if ($this->embed_tokens) {
			$start = $this->prev;
		}
		$ast = call_user_func(function() use($in_statement) {
			$name = null;
			if ($this->is('name')) {
				$value = $this->token->value;
				$this->next();
				$name = $value;
			}
			if ($in_statement && ! $name) {
				$this->unexpected();
			}
			$type = $in_statement ? 'defun' : 'function';
			// Get arguments
			$first = true;
			$args = array();
			while (! $this->is('punc', ')')) {
				if ($first) {
					$first = false;
				} else {
					$this->expect(',');
				}
				if (! $this->is('name')) {
					$this->unexpected();
				}
				$args[] = $this->token->value;
				$this->next();
			}
			$this->next();
			// Get function body
			$this->in_function++;
			$loop = $this->in_loop;
			$this->in_loop = 0;
			$body = $this->block_();
			$this->in_function--;
			$this->in_loop = $loop;
			return array($type, $name, $args, $body);
		});
		if ($this->embed_tokens) {
			$ast[0] = $this->add_tokens($ast[0], $start, $this->prev);
		}
		return $ast;
	}

	protected function if_() {
		$cond = $this->parenthesised();
		$body = $this->statement();
		$belse = null;
		if ($this->is('keyword', 'else')) {
			$this->next();
			$belse = $this->statement();
		}
		return array('if', $cond, $body, $else);
	}

	protected function block_() {
		$this->expect('{');
		$arr = array();
		while (! $this->is('punc', '}')) {
			if ($this->is('eof')) $this->unexpected();
			$arr[] = $this->statement();
		}
		$this->next();
		return $arr;
	}

	protected function switch_block_() {
		return $this->do_in_loop(function() {
			$this->expect('{');
			$arr = array();
			$cur = null;
			while (! $this->is('punc', '}')) {
				if ($this->is('eof')) $this->unexpected();
				if ($this->is('keyword', 'case')) {
					$this->next();
					$cur = array();
					$arr[] = array($this->expression(), &$cur);
					$this->expect(':');
				} elseif ($this->is('keyword', 'default')) {
					$this->next();
					$this->expect(':');
					$cur = array();
					$arr[] = array(null, &$cur);
				} else {
					if (! $cur) $this->unexpected();
					$cur[] = $this->statement();
				}
			}
			$this->next();
			return $arr;
		});
	}

	protected function try_() {
		$body = $this->block_();
		$bcatch = null;
		$bfinally = null;
		if ($this->is('keyword', 'catch')) {
			$this->next();
			$this->expect('(');
			if (! $this->is('name')) {
				$this->croak('Name expected');
			}
			$name = $this->token->value;
			$this->next();
			$this->expect(')');
			$bcatch = array($name, $this->block_());
		}
		if ($this->is('keyword', 'finally')) {
			$this->next();
			$bfinally = $this->block_();
		}
		if (! $bcatch && ! $bfinally) {
			$this->croak('Missing catch/finally blocks');
		}
		return array('try', $body, $bcatch, $bfinally);
	}

	protected function vardefs($no_in = null) {
		$arr = array();
		for (;;) {
			if (! $this->is('name')) $this->unexpected();
			$name = $this->token->value;
			$this->next();
			if ($this->is('operator', '=')) {
				$this->next();
				$arr[] = array($name, $this->expression(false, $no_in));
			} else {
				$arr[] = array($name);
			}
			if (! $this->is('punc', ',')) break;
			$this->next();
		}
		return $arr;
	}

	protected function var_($no_in = null) {
		return array('var', $this->vardefs($no_in));
	}

	protected function const_() {
		return array('const', $this->vardefs());
	}

	protected function new_() {
		$newexp = $this->expr_atom(false);
		if ($this->is('punc', '(')) {
			$this->next();
			$args = $this->expr_list(')');
		} else {
			$args = array();
		}
		return $this->subscripts(array('new', $newexp, $args), true);
	}

	protected function expr_atom($allow_calls = null) {
		if ($this->is('operator', 'new')) {
			$this->next();
			return $this->new_();
		}
		if ($this->is('operator') && in_array($this->token->value, ParseJS::$UNARY_PREFIX)) {
			$token_value = $this->token->value;
			$this->next();
			return $this->make_unary('unary-prefix', $token_value, $this->expr_atom($allow_calls));
		}
		if ($this->is('punc')) {
			switch ($this->token->value) {
				case '(':
					$this->next();
					$exp = $this->expression();
					$this->expect(')');
					return $this->subscripts($exp, $allow_calls);
				break;
				case '[':
					$this->next();
					return $this->subscripts($this->array_(), $allow_calls);
				break;
				case '{':
					$this->next();
					return $this->subscripts($this->object_(), $allow_calls);
				break;
			}
			$this->unexpected();
		}
		if ($this->is('keyword', 'function')) {
			$this->next();
			return $this->subscripts($this->function_(false), $allow_calls);
		}
		if (in_array($this->token->type, ParseJS::$ATOMIC_START_TOKEN)) {
			if ($this->token->type == 'regexp') {
				$atom = array('regexp', $this->token->value[0], $this->token->value[1]);
			} else {
				$atom = array($this->token->type, $this->token->value);
			}
			$this->next();
			return $this->subscripts($atom, $allow_calls);
		}
		$this->unexpected();
	}

	protected function expr_list($closing, $allow_trailing_comma = null, $allow_empty = null) {
		$first = true;
		$arr = array();
		while (! $this->is('punc', $closing)) {
			if ($first) {
				$first = false;
			} else {
				$this->expect(',');
			}
			if ($allow_trailing_comma && $this->is('punc', $closing)) break;
			if ($this->is('punc', ',') && $allow_empty) {
				$arr[] = array('atom', 'undefined');
			} else {
				$arr[] = $this->expression(false);
			}
		}
		$this->next();
		return $arr;
	}

	protected function array_() {
		return array('array', $this->expr_list(']', ! $this->exigent_mode, true));
	}

	protected function object_() {
		$first = true;
		$arr = array();
		while (! $this->is('punc', '}')) {
			if ($first) {
				$first = false;
			} else {
				$this-expect(',');
			}
			if (! $this->exigent_mode && $this->is('punc', '}')) {
				break;
			}
			$type = $this->token->type;
			$name = $this->as_property_name();
			if ($type == 'name' && ($name == 'set' || $name == 'get') && ! $this->is('punc', ':')) {
				$arr[] = array($this->as_name(), $this->function_(false), $name);
			} else {
				$this->expect(':');
				$arr[] = array($name, $this->expression(false));
			}
		}
		$this->next();
		return array('object', $arr);
	}

	protected function as_property_name() {
		switch ($this->token->type) {
			case 'num':
			case 'string':
				$value = $this->token->value;
				$this->next();
				return $value;
			break;
		}
		return $this->as_name();
	}

	protected function as_name() {
		switch ($this->token->type) {
			case 'name':
			case 'operator':
			case 'keyword':
			case 'atom':
				$value = $this->token->value;
				$this->next();
				return $value;
			break;
		}
		$this->unexpected();
	}

	protected function subscripts($expr, $allow_calls = null) {
		if ($this->is('punc', '.')) {
			$this->next();
			return $this->subscripts(array('dot', $expr, $this->as_name()), $allow_calls);
		}
		if ($this->is('punc', '[')) {
			$this->next();
			$exp = $this->expression();
			$this->expect(']');
			return $this->subscripts(array('sub', $expr, $exp), $allow_calls);
		}
		if ($allow_calls) {
			if ($this->is('punc', '(')) {
				$this->next();
				return $this->subscripts(array('call', $expr, $this->expr_list(')')), true);
			}
			if ($this->is('operator') && in_array($this->token->value, ParseJS::$UNARY_POSTFIX)) {
				$unary = $this->make_unary('unary-postfix', $this->token->value, $expr);
				$this->next();
				return $unary;
			}
		}
		return $expr;
	}

	protected function make_unary($tag, $op, $expr) {
		if (($op == '++' || $op == '--') && ! $this->is_assignable($expr)) {
			$this->croak('Invalid use of '.$op.' operator');
		}
		return array($tag, $op, $expr);
	}

	protected function expr_op($left, $min_prec, $no_in = null) {
		$op = ($this->is('operator')) ? $this->token->value : null;
		if ($op == 'in' && $no_in) {
			$op = null;
		}
		$prec = ($op !== null) ? ParseJS::$PRECEDENCE[$op] : null;
		if ($prec !== null && $prec > $min_prec) {
			$this->next();
			$right = $this->expr_op($this->expr_atom(true), $prec, $no_in);
			return $this->expr_op(array('binary', $op, $left, $right), $min_prec, $no_in);
		}
		return $left;
	}

	protected function expr_ops($no_in = null) {
		return $this->expr_op($this->expr_atom(true), 0, $no_in);
	}

	protected function maybe_conditional($no_in = null) {
		$expr = $this->expr_ops($no_in);
		if ($this->is('operator', '?')) {
			$this->next();
			$yes = $this->expression(false);
			$this->expect(':');
			return array('conditional', $expr, $yes, $this->expression(false, $no_in));
		}
		return $expr;
	}

	protected function is_assignable($expr) {
		if (! $this->exigent_mode) return true;
		switch ($expr[0]) {
			case 'dot':
			case 'sub':
			case 'new':
			case 'call':
				return true;
			break;
			case 'name':
				return ($expr[1] != 'this');
			break;
		}
	}

	protected function maybe_assign($no_in = null) {
		$left = $this->maybe_conditional($no_in);
		$val = $this->token->value;
		if ($this->is('operator') && in_array($val, ParseJS::$ASSIGNMENT)) {
			if ($this->is_assignable($left)) {
				$this->next();
				return array('assign', ParseJS::$ASSIGNMENT[$val], $left, $this->maybe_assign($no_in));
			}
			$this->croak('Invalid assignment');
		}
		return $left;
	}

	protected function expression($commas = null, $no_in = null) {
		if ($commas === null) {
			$commas = true;
		}
		$expr = $this->maybe_assign($no_in);
		if ($commas && $this->is('punc', ',')) {
			$this->next();
			return array('seq', $expr, $this->expression(true, $no_in));
		}
		return $expr;
	}

	protected function do_in_loop($cont) {
		try {
			$this->in_loop++;
			if (is_string($cont) && method_exists($this, $cont)) {
				$this->$cont();
			} else {
				$cont();
			}
		} catch (Exception $e) { }
		$this->in_loop--;
	}

}

/* End of file javascript-parser.php */
