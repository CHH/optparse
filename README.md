# Optparse — Another Command Line Argument Parser

## Install

1\. Get [composer](http://getcomposer.org).

2\. Put this into your local `composer.json`:
```
{
  "require": {
    "chh/optparse": "*@dev"
  }
}
```

3\. `php composer.phar install`

## Use

There are two things you will define in the parser:

 - _Flags_, arguments which start with one or two dashes and are
   considered as options of your program.
 - _Arguments_, everything else which is not a flag.

The main point of interest is the `CHH\Optparse\Parser`, which you can
use to define _Flags_ and _Arguments_.

### Flags

To define a flag, pass the flag's name to the `addFlag` method:

```php
<?php

$parser = new CHH\Optparse\Parser;

$parser->addFlag("help");
$parser->parse();

if ($parser["help"]) {
    echo $parser->usage();
    exit;
}
```

A flag defined with `addFlag` is by default available as `--$flagName`.
To define another name (e.g. a short name) for the flag, pass it as the
value of the `alias` option in the options array:

```php
<?php
$parser->addFlag("help", ["alias" => "-h"]);
```

This way the `help` flag is available as `--help` _and_ `-h`.

Flags don't expect values following them by default. To turn this on set the flag's `has_value` option
to `true`:

```php
<?php

$parser->addFlag("name", ["has_value" => true]);
$parser->parse(['--name', 'John']);

echo "Hello World {$parser["name"]}!\n";
```

The parser also supports callbacks for flags. These are passed to
`addFlag` as last argument. The callback is called everytime the parser
encounters the flag. It gets passed a reference to the flag's value (`true` if it
hasn't one). Use cases for this include splitting a string in pieces or
running a method when a flag is passed:

```php
<?php

$parser = new Parser;

function usage_and_exit()
{
    global $parser;
    echo $parser->usage(), "\n";
    exit;
}

$parser->addFlag("help", ['alias' => '-h'], "usage_and_exit");

$parser->addFlag("queues", ["has_value" => true], function(&$value) {
    $value = explode(',', $value);
});
```

The call to `parse` takes an array of arguments, or falls back to using
the arguments from `$\_SERVER['argv']`. The `parse` method throws an
`CHH\Optparse\ArgumentException` when a required flag or argument is missing, so make
sure to catch this Exception and provide the user with a nice error
message.

The parser is also able to generate a usage message for the command by
looking at the defined flags and arguments. Use the `usage` method to
retrieve it.

### Named Arguments

Named arguments can be added by using the `addArgument` method, which
takes the argument's name as first argument and then an array of
options.

As opposed to flags, the order **matters** in which you define your
arguments.

Variable length arguments can be defined by setting the `var_arg` option
to `true` in the options array. Variable arguments can only be at the
last position, and arguments defined after an variable argument are
never set.

```php
<?php

$parser->addArgument("files", ["var_arg" => true]);

// Will always be null, because the value will be consumed by the
// "var_arg" enabled argument.
$parser->addArgument("foo");

$parser->parse(["foo", "bar", "baz"]);

foreach ($parser["files"] as $file) {
    echo $file, "\n";
}
// Output:
// foo
// bar
// baz
```

## License

Copyright (c) 2012 Christoph Hochstrasser

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.