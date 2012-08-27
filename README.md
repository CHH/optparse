# Optparse — an easy to use Parser for Command Line Arguments

## Install

1. Get [composer](http://getcomposer.org).
2. Put this into your local `composer.json`:
   ```
   {
     "require": {
       "chh/optparse": "*@dev"
     }
   }
   ```
3. `php composer.phar install`

## Use

There are two things you will define in the parser:

 - _Flags_, arguments which start with one or two dashes and are
   considered as options of your program.
 - _Arguments_, everything else which is not a flag.

The main point of interest is the `CHH\Optparse\Parser`, which you can
use to define _Flags_ and _Arguments_.

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
$parser->addFlag("help", array("alias" => "-h"));
```

This way the `help` flag is available as `--help` _and_ `-h`.

The call to `parse` takes an array of arguments, or falls back to using
the arguments from `$\_SERVER['argv']`.

