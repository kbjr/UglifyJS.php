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

	public $input        = null;
	public $token        = null;
	public $prev         = null;
	public $peeked       = null;
	public $in_function  = 0;
	public $in_loop      = 0;
	public $labels       = array();

	public $exigent_mode = null;
	public $embed_tokens = null;

// ----------------------------------------------------------------------------
//  Public functions

	public function __construct($input, $exigent_mode = false, $embed_tokens = false) {
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

	public function raise($msg, $line, $col, $pos) {
		throw new JS_Parse_Error($msg, $line, $col, $pos);
	}

	public function croak($msg, $line = null, $col = null, $pos = null) {
		$ctx = $this->input->context();
		$this->raise(
			$msg,
			(($line !== null) ? $line : $ctx->line),
			(($col  !== null) ? $col  : $ctx->col),
			(($pos  !== null) ? $pos  : $ctx->pos)
		);
	}

	public function token_error($token, $msg) {
		$this->croak($msg, $token->line, $token->col);
	}

	public function unexpected($token = null) {
		if (! $token) {
			$token = $this->token;
		}
		$this->token_error($token, 'Unexpected token '.$token->type.' ('.$token->value.')');
	}

	public function expect_token($type, $val = null) {
		if ($this->is($type, $val)) {
			return $this->next();
		}
		$this->token_error($this->token, 'Unexpected token '.$this->token->type.', expected '.$type);
	}

	public function expect($punc) {
		return $this->expect_token('punc', $punc);
	}

// ----------------------------------------------------------------------------
//  Semicolon handling functions

	public function can_insert_semicolon() {
		return (! $this->exigent_mode && (
			$this->token->nlb || $this->is('eof') || $this->is('punc', '}')
		));
	}

	public function semicolon() {
		if ($this->is('punc', ';')) {
			$this->next();
		} elseif (! $this->can_insert_semicolon()) {
			$this->unexpected();
		}
	}

// ----------------------------------------------------------------------------
//  Internal helper functions

	public function is($type, $value = null) {
		return ParseJS::is_token($this->token, $type, $value);
	}

	public function next() {
		$this->prev = $this->token;
		if ($this->peeked) {
			$this->token = $this->peeked;
			$this->peeked = null;
		} else {
			$this->token = $this->input->next_token();
		}
		return $this->token;
	}

	public function parenthesised() {
		$this->expect('(');
		$exp = $this->expression();
		$this->expect(')');
		return $exp;
	}

	public function add_tokens($str, $start = null, $end = null) {
		if ($str instanceof NodeWithToken) {
			return $str;
		} else {
			return new NodeWithToken($str, $start, $end);
		}
	}

	public function statement() {
		if ($this->embed_tokens) {
			$start = $this->token;
		}
		$argv = func_get_args();
		$self =& $this;
		$ast = call_user_func(function() use(&$self, $argv) {
			if ($self->is('operator', '/')) {
				$self->peeked = null;
				$self->token = $self->input->next_token(true);
			}
			switch ($self->token->type) {
				case 'num':
				case 'string':
				case 'regexp':
				case 'operator':
				case 'atom':
					return $self->simple_statement();
				break;
				case 'name':
					if (ParseJS::is_token($self->peek(), 'punc', ':')) {
						$self->next();
						$self->next();
						return $self->labeled_statement($self->token->value);
					} else {
						return $self->simple_statement();
					}
				break;
				case 'punc':
					switch ($self->token->value) {
						case '{':
							return array('block', $self->block_());
						break;
						case '[':
						case '(':
							return $self->simple_statement();
						break;
						case ';':
							$self->next();
							return array('block');
						break;
					}
				break;
				case 'keyword':
					$token_value = $self->token->value;
					$self->next();
					switch($token_value) {
						case 'break':
						case 'continue':
							return $this->break_cont($token_value);
						break;
						case 'debugger':
							$self->semicolon();
							return array('debugger');
						break;
					}
				break;
				case 'do':
					$body = $self->do_in_loop('statement');
					$self->expect_token('keyword', 'while');
					$paren = $self->parenthesised();
					$self->semicolon();
					return array('do', $paren, $body);
				break;
				case 'for':
					return $self->for_();
				break;
				case 'function':
					return $self->function_(true);
				break;
				case 'if':
					return $self->if_();
				break;
				case 'return':
					if (! $self->in_function) {
						$self->croak('"return" outside of function');
					}
					if ($self->is('punc', ';')) {
						$self->next();
						return array('return', null);
					} elseif ($self->can_insert_semicolon()) {
						return array('return', null);
					} else {
						$exp = $self->expression();
						$self->semicolon();
						return array('return', $exp);
					}
				break;
				case 'switch':
					return array('switch', $self->parenthesised(), $self->switch_block_());
				break;
				case 'throw':
					$exp = $self->expression();
					$self->semicolon();
					return array('throw', $exp);
				break;
				case 'try':
					return $self->try_();
				break;
				case 'var':
					$var = $self->var_();
					$self->semicolon();
					return $var;
				break;
				case 'const':
					$const = $self->const_();
					$self->semicolon();
					return $const;
				break;
				case 'while':
					return array('while', $self->parenthesised(), $self->do_in_loop('statement'));
				break;
				case 'with':
					return array('with', $self->parenthesised(), $self->statement());
				break;
				default:
					$self->unexpected();
				break;
			}
		});
		if ($this->embed_tokens) {
			$ast[0] = $this->add_tokens($ast[0], $start, $this->prev);
		}
		return $ast;
	}

	public function labeled_statement($label) {
		$this->labels[] = $label;
		$start = $this->token;
		$stat = $this->statement();
		if ($this->exigent_mode && ! in_array($stat[0], ParseJS::$STATEMENTS_WITH_LABELS)) {
			$this->unexpected($start);
		}
		array_pop($this->labels);
		return array('label', $label, $stat);
	}

	public function simple_statement() {
		$exp = $this->expression();
		$this->semicolon();
		return array('stat', $exp);
	}

	public function break_cont($type) {
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

	public function for_() {
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

	public function regular_for($init) {
		$this->expect(';');
		$test = $this->is('punc', ';') ? null : $this->expression();
		$this->expect(';');
		$step = $this->is('punc', ')') ? null : $this->expression();
		$this->expect(')');
		return array('for', $init, $test, $step, $this->do_in_loop('statement'));
	}

	public function for_in($init) {
		$lhs = ($init[0] == 'var') ? array('name', $init[1][0]) : $init;
		$this->next();
		$obj = $this->expression();
		$this->expect(')');
		return array('for-in', $init, $lhs, $obj, $this->do_in_loop('statement'));
	}

	public function function_($in_statement = null) {
		if ($this->embed_tokens) {
			$start = $this->prev;
		}
		$self =& $this;
		$ast = call_user_func(function() use(&$self, $in_statement) {
			$name = null;
			if ($self->is('name')) {
				$value = $self->token->value;
				$self->next();
				$name = $value;
			}
			if ($in_statement && ! $name) {
				$self->unexpected();
			}
			$type = $in_statement ? 'defun' : 'function';
			// Get arguments
			$first = true;
			$args = array();
			while (! $self->is('punc', ')')) {
				if ($first) {
					$first = false;
				} else {
					$self->expect(',');
				}
				if (! $self->is('name')) {
					$self->unexpected();
				}
				$args[] = $self->token->value;
				$self->next();
			}
			$self->next();
			// Get function body
			$self->in_function++;
			$loop = $self->in_loop;
			$self->in_loop = 0;
			$body = $self->block_();
			$self->in_function--;
			$self->in_loop = $loop;
			return array($type, $name, $args, $body);
		});
		if ($this->embed_tokens) {
			$ast[0] = $this->add_tokens($ast[0], $start, $this->prev);
		}
		return $ast;
	}

	public function if_() {
		$cond = $this->parenthesised();
		$body = $this->statement();
		$belse = null;
		if ($this->is('keyword', 'else')) {
			$this->next();
			$belse = $this->statement();
		}
		return array('if', $cond, $body, $else);
	}

	public function block_() {
		$this->expect('{');
		$arr = array();
		while (! $this->is('punc', '}')) {
			if ($this->is('eof')) $this->unexpected();
			$arr[] = $this->statement();
		}
		$this->next();
		return $arr;
	}

	public function switch_block_() {
		$self =& $this;
		return $this->do_in_loop(function() use(&$self) {
			$self->expect('{');
			$arr = array();
			$cur = null;
			while (! $self->is('punc', '}')) {
				if ($self->is('eof')) $self->unexpected();
				if ($self->is('keyword', 'case')) {
					$self->next();
					$cur = array();
					$arr[] = array($self->expression(), &$cur);
					$self->expect(':');
				} elseif ($self->is('keyword', 'default')) {
					$self->next();
					$self->expect(':');
					$cur = array();
					$arr[] = array(null, &$cur);
				} else {
					if (! $cur) $self->unexpected();
					$cur[] = $self->statement();
				}
			}
			$self->next();
			return $arr;
		});
	}

	public function try_() {
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

	public function vardefs($no_in = null) {
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

	public function var_($no_in = null) {
		return array('var', $this->vardefs($no_in));
	}

	public function const_() {
		return array('const', $this->vardefs());
	}

	public function new_() {
		$newexp = $this->expr_atom(false);
		if ($this->is('punc', '(')) {
			$this->next();
			$args = $this->expr_list(')');
		} else {
			$args = array();
		}
		return $this->subscripts(array('new', $newexp, $args), true);
	}

	public function expr_atom($allow_calls = null) {
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

	public function expr_list($closing, $allow_trailing_comma = null, $allow_empty = null) {
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

	public function array_() {
		return array('array', $this->expr_list(']', ! $this->exigent_mode, true));
	}

	public function object_() {
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

	public function as_property_name() {
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

	public function as_name() {
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

	public function subscripts($expr, $allow_calls = null) {
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

	public function make_unary($tag, $op, $expr) {
		if (($op == '++' || $op == '--') && ! $this->is_assignable($expr)) {
			$this->croak('Invalid use of '.$op.' operator');
		}
		return array($tag, $op, $expr);
	}

	public function expr_op($left, $min_prec, $no_in = null) {
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

	public function expr_ops($no_in = null) {
		return $this->expr_op($this->expr_atom(true), 0, $no_in);
	}

	public function maybe_conditional($no_in = null) {
		$expr = $this->expr_ops($no_in);
		if ($this->is('operator', '?')) {
			$this->next();
			$yes = $this->expression(false);
			$this->expect(':');
			return array('conditional', $expr, $yes, $this->expression(false, $no_in));
		}
		return $expr;
	}

	public function is_assignable($expr) {
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

	public function maybe_assign($no_in = null) {
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

	public function expression($commas = null, $no_in = null) {
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

	public function do_in_loop($cont) {
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
