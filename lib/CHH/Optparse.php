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
namespace CHH\Optparse;

class Exception extends \Exception
{}

class ParseException extends Exception
{}

class ArgumentException extends Exception
{}

class Flag
{
    public
        $name,
        $callback,
        $aliases = array(),
        $hasValue = false,
        $defaultValue,
        $var,
        $help;

    function __construct($name, $options = array(), $callback = null)
    {
        $this->name = $name;
        $this->callback = $callback;

        $this->aliases = array_merge(array("--$name"), (array) @$options["alias"]);
        $this->defaultValue = @$options["default"];
        $this->hasValue = (bool) @$options["has_value"];
        $this->help = @$options["help"];

        if (array_key_exists("var", $options)) $this->var =& $options["var"];
    }

    function __toString()
    {
        $s = join('|', $this->aliases);

        if ($this->hasValue) {
            $s = "$s <{$this->name}>";
        }

        return "[$s]";
    }
}

class Argument
{
    public
        $name,
        $vararg = false,
        $required = false,
        $defaultValue,
        $help;

    function __construct($name, $options = array())
    {
        $this->name = $name;
        $this->vararg = (bool) @$options["var_arg"];
        $this->required = (bool) @$options["required"];
        $this->defaultValue = @$options["default"];
        $this->help = @$options["help"];
    }

    function __toString()
    {
        $arg = "<{$this->name}>";

        if ($this->vararg) {
            $arg = "$arg ...";
        }

        if (!$this->required) {
            return "[$arg]";
        }

        return $arg;
    }
}

class Parser implements \ArrayAccess
{
    protected
        # The command's name, printed at the front of the usage line.
        $name,

        # Description of the command. Used in the usage message.
        $description,

        # Array of example usages, printed in the usage message.
        $examples = array(),

        # Defined flags, by alias.
        $flags = array(),

        # Defined arguments, by position.
        $args = array(),

        # Parsed flags, by name.
        $parsedFlags = array(),

        $parsedNamedArgs = array(),

        # Parsed arguments, by name or position.
        $parsedArgs = array();

    static function build($callback)
    {
        $parser = new static;

        if ($callback instanceof \Closure and is_callable(array($callback, "bindTo"))) {
            $callback = $callback->bindTo($parser);
        }

        call_user_func($callback, $parser);

        return $parser;
    }

    function __construct($description = '', $name = null, $examples = array())
    {
        $this->description = $description;
        $this->name = $name;
        $this->examples = $examples;
    }

    function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    function setExamples($examples)
    {
        $this->examples = $examples;
        return $this;
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
            // reset value
            $value = null;

            if (substr($arg, 0, 1) === '-') {
                if (preg_match('/^(.+)=(.+)$/', $arg, $matches)) {
                    $arg = $matches[1];
                    $value = $matches[2];
                }

                if (!$flag = @$this->flags[$arg]) {
                    throw new ParseException(sprintf('Flag "%s" is not defined.', $arg));
                }

                unset($args[$pos]);

                if ($flag->hasValue) {
                    if (!isset($value)) {
                        $value = $args[$pos + 1];
                        unset($args[$pos + 1]);
                    }
                } else {
                    $value = true;
                }

                if (null !== $flag->callback) {
                    call_user_func_array($flag->callback, array(&$value));
                }

                # Set the reference given as the flag's 'var'.
                $flag->var = $this->parsedFlags[$flag->name] = $value;
            }
        }

        # TODO: Refactor this so this fits into the above foreach
        foreach ($this->flags as $flag) {
            if (!array_key_exists($flag->name, $this->parsedFlags)) {
                $flag->var = $this->parsedFlags[$flag->name] = $flag->defaultValue;
            }
        }

        $this->parsedArgs = $args = array_values($args);

        $pos = 0;

        foreach ($this->args as $arg) {
            if ($arg->required and !isset($args[$pos])) {
                throw new ArgumentException(sprintf(
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

            $this->parsedNamedArgs[$arg->name] = $value;
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
    # The value is available after `Parser::parse()` was called.
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

    # Retrieves all parsed arguments.
    #
    # Returns an Array.
    function args()
    {
        return $this->parsedArgs;
    }

    function count()
    {
        return count($this->args());
    }

    # Get a flag or named argument.
    #
    # name - Flag or argument name.
    #
    # Returns the value of the flag or argument.
    function get($name)
    {
        return $this->flag($name) ?: $this->arg($name);
    }

    # Returns the argument by position or name.
    #
    # pos - Position or name.
    #
    # Returns the argument's value or Null when not set.
    function arg($pos)
    {
        if (array_key_exists($pos, $this->parsedNamedArgs)) {
            return $this->parsedNamedArgs[$pos];
        }

        if (array_key_exists($pos, $this->parsedArgs)) {
            return $this->parsedArgs[$pos];
        }
    }

    # Returns the flag's value by name.
    #
    # name - Name of the flag.
    #
    # Returns the flag's value or Null when not set.
    function flag($name)
    {
        if (array_key_exists($name, $this->parsedFlags)) {
            return $this->parsedFlags[$name];
        }
    }

    # Slices the parsed arguments.
    #
    # start  - Index where slicing should start.
    # length - Optional length of the returned array (default: null).
    #
    # Returns an Array.
    function slice($start, $length = null)
    {
        return array_slice($this->parsedArgs, $start, $length);
    }

    # Generates a usage message from the defined flags and arguments.
    #
    # Returns a String.
    function usage()
    {
        $flags = join(' ', array_unique(array_values($this->flags)));
        $args = join(' ', $this->args);
        $script = $this->name ?: 'php ' . basename($_SERVER["SCRIPT_NAME"]);

        $usage = "Usage: $script $flags $args";

        if ($this->examples) {
            $usage .= "\n\nExamples\n\n" . join("\n", $this->examples);
        }

        if ($this->description) {
            $usage .= "\n\n{$this->description}";
        }

        return $usage;
    }

    function longUsage()
    {
        $long = '';
        $short = $this->usage();

        $flags = join("\n", array_map(
            function($flag) {
                $aliases = $flag->aliases;

                # Sort aliases by length, so short names are first.
                array_multisort(array_map('strlen', $flag->aliases), $aliases);

                $desc = "  " . join(', ', $aliases);

                if ($flag->hasValue) {
                    $desc .= " <{$flag->name}>";
                }

                if ($flag->defaultValue !== null) {
                    $desc .= sprintf(' (default: %s)', var_export($flag->defaultValue, true));
                }

                if ($flag->help) {
                    $desc .= ": {$flag->help}";
                }

                return $desc;
            },
            array_unique($this->flags)
        ));

        $args = join("\n", array_map(
            function($arg) {
                $desc = "  {$arg->name}" . ($arg->required ? " (required)" : '');

                if ($arg->help) {
                    $desc .= ": {$arg->help}";
                }

                return $desc;
            },
            $this->args
        ));

        $long .= $short;

        if ($this->args) {
            $long .= "\n\nArguments:\n\n$args";
        }

        if ($this->flags) {
            $long .= "\n\nFlags:\n\n$flags";
        }

        return $long;
    }

    # Allows retrieving of flag and argument values by using the
    # parser as array.
    #
    # offset - Name of flag or argument.
    #
    # Returns the value of the flag/argument or Null when not set.
    function offsetGet($offset)
    {
        return $this->get($offset);
    }

    # Checks if the given flag or argument is set.
    #
    # Returns True when the flag/argument is set, False otherwise.
    function offsetExists($offset)
    {
        return null !== $this->get($offset);
    }

    function offsetSet($offset, $value) {}
    function offsetUnset($offset) {}
}
