<?php

# Implements an option parser.
#
# Examples
#
#   $parser = new optparse\Parser;
#
#   $parser->addFlag("help", ["alias" => "-h"]);
#   $parser->addFlag("chdir", ["alias" => "-C"]);
#   $parser->addArgument("files", ["var_arg" => true, "required" => true]);
#
#   $parser->parse(array("--help", "foo", "bar", "baz"));
#
#   echo var_export($parser["help"]);
#   # Output:
#   # true
#
#   foreach ($parser["files"] as $file) {
#       echo $file, "\n";
#   }
#   # Output:
#   # foo
#   # bar
#   # baz
#
namespace optparse;

class Flag
{
    public $name;
    public $callback;
    public $aliases = array();
    public $hasValue = false;
    public $required = false;
    public $defaultValue;
    public $var;

    function __construct($name, $options = array(), $callback = null)
    {
        $this->name = $name;
        $this->callback = $callback;

        $this->aliases = array_merge(array("--$name"), (array) @$options["alias"]);
        $this->required = (bool) @$options["required"];
        $this->defaultValue = @$options["default"];
        $this->hasValue = (bool) @$options["has_value"];

        if (array_key_exists("var", $options)) $this->var =& $options["var"];
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
    public $vararg = false;
    public $required = false;
    public $defaultValue;

    function __construct($name, $options = array())
    {
        $this->name = $name;
        $this->vararg = (bool) @$options["var_arg"];
        $this->required = (bool) @$options["required"];
        $this->defaultValue = @$options["default"];
    }

    function __toString()
    {
        $arg = "<{$this->name}>";

        if ($this->vararg) {
            $arg = "$arg...";
        }

        if (!$this->required) {
            return "[$arg]";
        }

        return $arg;
    }
}

class Exception extends \Exception
{}

class ParseException extends Exception
{}

class RequiredArgumentMissingException extends Exception
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

    # Public: Parse the array of flags.
    #
    # args - Array of arguments. When null `$_SERVER['argv']` is used (sans first item).
    #
    # Returns Nothing.
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

                # Set the reference given as the flag's 'var'.
                $flag->var = $this->parsedFlags[$flag->name] = $value;
            }
        }

        foreach ($this->flags as $flag) {
            if (!array_key_exists($flag->name, $this->parsedFlags)) {
                if ($flag->required) {
                    throw new RequiredArgumentMissingException(sprintf(
                        'Missing required argument "%s"', $name
                    ));
                } else {
                    $flag->var = $this->parsedFlags[$flag->name] = $flag->defaultValue;
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

            if (isset($args[$pos])) {
                if ($arg->vararg) {
                    $value = array_slice($args, $pos);
                    $pos += count($value);
                } else {
                    $value = $args[$pos];
                    $pos++;
                }
            } else {
                $value = $arg->defaultValue;
            }

            $this->parsedArgs[$arg->name] = $value;
        }
    }

    # Public: Adds a flag to the parser.
    #
    # Flags are arguments which typically begin with either one or two dashes.
    #
    # name     - Name of the flag. By default the flag's argument name is "--$name".
    # options  - Array of options (default: array()):
    #            'alias'     - Alias(es) for the flag, for example '-h'. By default the only
    #                          alias is the flag's name prefixed with two dashes.
    #            'has_value' - Denotes that the argument following this flag
    #                          is the flag's value (default: false).
    #            'default'   - Default value, when the flag is not passed (default: null).
    #            'required'  - Throw an exception when the flag is omitted (default: false).
    #            'var'       - Reference to a variable which should be set to the flag's value.
    # callback - A callback which is called when the flag is present and is passed the flag's
    #            value. Can be used to run actions or to process the flags value (default: null).
    #
    # Returns the Parser.
    function addFlag($name, $options = array(), $callback = null)
    {
        $flag = new Flag($name, $options, $callback);

        foreach ($flag->aliases as $alias) {
            $this->flags[$alias] = $flag;
        }

        return $this;
    }

    # Public: Assigns the variable to the value of the flag.
    #
    # The values is available after Parser::parse() was called.
    #
    # name    - Name of the flag.
    # var     - Variable which should be set to the flag's value.
    # options - See `addFlag` (default: array()).
    #
    # Returns the Parser.
    function addFlagVar($name, &$var, $options = array())
    {
        $options["var"] =& $var;
        return $this->addFlag($name, $options);
    }

    # Public: Adds a named argument.
    #
    # name    - Name of the argument. Can be used to retrieve the argument via the `arg` method.
    # options - Array of options (default: array()):
    #           'var_arg'  - Denotes that the argument has multiple values (default: false).
    #           'default'  - Default value, when the argument is optional and not given (default: null).
    #           'required' - Makes the argument required (default: false).
    #
    # Returns the Parser.
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
        $args = join(' ', $this->args);

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
