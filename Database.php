<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

	private array $placeholder_types = ['string', 'int', 'float', 'bool', 'null'];

    private array $placeholders = [
	    '?' => [
		    'types' => true,
	    ],
	    '?d' => [
	    	'convert' => 'int',
		    'null' => true
	    ],
	    '?f' => [
		    'convert' => 'float',
		    'null' => true
	    ],
	    '?a' => [
		    'array' => true
	    ],
	    '?#' => [
		    'ident' => true
	    ],
    ];

    private string $skip = '##skip##';

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

	/**
	 * @throws Exception
	 */
	private function placeholderType(mixed $arg): mixed
    {
	    $allowed_type = false;
	    foreach($this->placeholder_types as $t){
		    if(('is_' . $t)($arg)){
			    if($t == 'bool'){
				    $arg = (int) $arg;
			    }
			    else if($t == 'string'){
				    $arg = "'$arg'";
			    }
			    else if($t == 'null'){
				    $arg = 'NULL';
			    }
			    $allowed_type = true;
			    break;
		    }
	    }
	    if(!$allowed_type){
		    throw new Exception('Unsupported type');
	    }
	    return $arg;
    }

	/**
	 * @throws Exception
	 */
	public function buildQuery(string $query, array $args = []): string
    {
    	$pattern = '/\?[dfa#]?|{(.+?)}/';
    	preg_match_all($pattern, $query, $matches);
    	if(!$matches[0]){
		    return $query;
	    }
    	foreach($matches[0] as $i => $m){
		    $arg = $args[$i];
    		if($matches[1][$i] != ''){
			    $arg = (array) $arg;
			    if(in_array($this->skip, $arg, true)){
				    $args[$i] = '';
			    	continue;
			    }
			    $args[$i] = $this->buildQuery($matches[1][$i], $arg);
    			continue;
		    }
    		$placeholder = $this->placeholders[$m];
			if(isset($placeholder['types'])){
				$arg = $this->placeholderType($arg);
			}
			else if(isset($placeholder['convert'])){
				settype($arg, $placeholder['convert']);
			}
			else if(isset($placeholder['array'])){
				array_walk($arg, function(&$value){
					$value = $this->placeholderType($value);
				});
				if(!array_is_list($arg)){
					array_walk($arg, function (&$v, $k){
						$v = "`$k` = $v";
					});
				}
				$arg = implode(', ', $arg);
			}
			else if(isset($placeholder['ident'])){
				$arg = (array) $arg;
				$arg = '`' . implode('`, `', $arg) . '`';
			}
		    $args[$i] = $arg;
	    }
	    return preg_replace_callback(
	    	$pattern,
		    function () use (&$args) {
			    return array_shift($args);
		    },
		    $query
	    );
    }

    public function skip(): string
    {
    	return $this->skip;
    }
}
