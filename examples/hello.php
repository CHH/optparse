<?php

require __DIR__ . "/../vendor/autoload.php";

use CHH\Optparse;

$parser = new Optparse\Parser("Says Hello");

$parser->addFlag("help", array("alias" => "-h"), function() use ($parser) {
    fwrite(STDERR, "{$parser->usage()}\n");
    exit(1);
});

$parser->addFlag("shout", array("alias" => "-S"));
$parser->addArgument("name", array("required" => true));

$parser->parse();

$msg = "Hello {$parser["name"]}!";

if ($parser["shout"]) {
    $msg = strtoupper($msg);
}

echo "$msg\n";
