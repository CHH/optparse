<?php

namespace optparse;

/*
    $parser = new optparse\Parser;
    $parser->flag("help", ["alias" => "-h", "default" => false]);

    # Map function as last argument.
    $parser->flag("queues", ["default" => [], "has_value" => true], function($val) {
        return explode(',', $val);
    });

    $parser->parse($_SERVER['argv']);

    if ($parser["help"]) {
        echo $parser->usage();
        exit;
    }
*/

class Flag
{
    public $name;

    public $callback;
    public $aliases = array();

    public $hasValue = false;
    public $required = false;
    public $defaultValue;

    function __construct($name, $options = array(), $callback = null)
    {
        $this->name = $name;
        $this->callback = $callback;

        $this->aliases = array_merge(array("--$name"), (array) @$options["alias"]);
        $this->required = (bool) @$options["required"];
        $this->defaultValue = @$options["default"];
        $this->hasValue = (bool) @$options["has_value"];
    }

    function __toString()
    {
        $s = join('|', $this->aliases);

        if ($this->hasValue) {
            $s = "$s <{$this->name}>";
        }

        if (!$this->required) {
            $s = "[$s]";
        }

        return $s;
    }
}

class Argument
{
    public $name;
    public $count;
    public $required = false;
    public $defaultValue;

    function __construct($name, $options = array())
    {
        $this->name = $name;
        $this->count = @$options["count"] ?: 1;
        $this->required = (bool) @$options["required"];
        $this->defaultValue = @$options["defaultValue"];
    }
}

class ParseException extends \Exception
{}

class RequiredArgumentMissingException extends \Exception
{}

class Parser implements \ArrayAccess
{
    protected
        $description,
        $flags = array(),
        $args = array(),

        $parsedFlags = array(),
        $parsedArgs = array();

    function __construct($description = '')
    {
        $this->description = $description;
    }

    function parse($args = null)
    {
        if ($args === null) {
            $args = array_slice($_SERVER['argv'], 1);
        }

        foreach ($args as $pos => $arg) {
            if (substr($arg, 0, 1) === '-') {
                if (!$flag = @$this->flags[$arg]) {
                    throw new ParseException(sprintf(
                        'Flag "%s" is not defined.', $arg
                    ));
                }

                unset($args[$pos]);

                if ($flag->hasValue) {
                    $value = $args[$pos + 1];
                    unset($args[$pos + 1]);
                } else {
                    $value = true;
                }

                if (null !== $flag->callback) {
                    $value = call_user_func($flag->callback, $value);
                }

                $this->parsedFlags[$flag->name] = $value;
            }
        }

        foreach ($this->flags as $flag) {
            if (!array_key_exists($flag->name, $this->parsedFlags)) {
                if ($flag->required) {
                    throw new RequiredArgumentMissingException(sprintf(
                        'Missing required argument "%s"', $name
                    ));
                } else {
                    $this->parsedFlags[$flag->name] = $flag->defaultValue;
                }
            }
        }

        $this->parsedArgs = $args = array_values($args);

        $pos = 0;

        foreach ($this->args as $arg) {
            if ($arg->required and !isset($args[$pos]) and !isset($args[$pos + $arg->count - 1])) {
                throw new ParseException(sprintf(
                    'Missing required argument "%s"', $arg->name
                ));
            }

            if ($arg->count === 1) {
                $value = $args[$pos];
            } else {
                $value = array_slice($args, $pos, $arg->count);
            }

            $pos += $arg->count;
            $this->parsedArgs[$arg->name] = $value;
        }
    }

    function addFlag($name, $options = array(), $callback = null)
    {
        $flag = new Flag($name, $options, $callback);

        foreach ($flag->aliases as $alias) {
            $this->flags[$alias] = $flag;
        }

        return $this;
    }

    function addArgument($name, $options = array())
    {
        $arg = new Argument($name, $options);
        $this->args[] = $arg;

        return $this;
    }

    function args()
    {
        return $this->parsedArgs;
    }

    function get($name)
    {
        return $this->flag($name) ?: $this->arg($name);
    }

    function arg($pos)
    {
        if (array_key_exists($pos, $this->parsedArgs)) {
            return $this->parsedArgs[$pos];
        }
    }

    function flag($name)
    {
        if (array_key_exists($name, $this->parsedFlags)) {
            return $this->parsedFlags[$name];
        }
    }

    function slice($start, $length = null)
    {
        return array_slice($this->parsedArgs, $start, $length);
    }

    function usage()
    {
        $flags = join(' ', array_unique(array_values($this->flags)));

        $args = join(' ', array_map(
            function($arg) {
                if (!$arg->required) {
                    return "[<{$arg->name}>]";
                }

                return "<{$arg->name}>";
            },
            $this->args
        ));

        return <<<EOT
Usage: $flags $args

{$this->description}
EOT;
    }

    function offsetGet($offset)
    {
        return $this->get($offset);
    }

    function offsetExists($offset)
    {
        return isset($this->parsedFlags[$offset]);
    }

    function offsetSet($offset, $value) {}
    function offsetUnset($offset) {}
}
