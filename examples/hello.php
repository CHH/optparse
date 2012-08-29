<?php

require __DIR__ . "/../vendor/autoload.php";

use CHH\Optparse;

$parser = new Optparse\Parser("Says Hello");

function usage_and_exit()
{
    global $parser;

    fwrite(STDERR, "{$parser->usage()}\n");
    exit(1);
}

$parser->addFlag("help", array("alias" => "-h"), "usage_and_exit");
$parser->addFlag("shout", array("alias" => "-S"));
$parser->addArgument("name", array("required" => true));

try {
    $parser->parse();
} catch (Optparse\Exception $e) {
    fwrite(STDERR, "{$e->getMessage()}\n\n");
    usage_and_exit();
}

$msg = "Hello {$parser["name"]}!";

if ($parser["shout"]) {
    $msg = strtoupper($msg);
}

echo "$msg\n";
