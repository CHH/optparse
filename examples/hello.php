<?php

require __DIR__ . "/../vendor/autoload.php";

use CHH\Optparse;

$parser = new Optparse\Parser("Says Hello", "hello");

function usage_and_exit()
{
    global $parser;

    fwrite(STDERR, "{$parser->longUsage()}\n");
    exit(1);
}

$parser->addFlag("help", array("alias" => "-h", "help" => "Displays this help message"), "usage_and_exit");
$parser->addFlag("shout", array("alias" => "-S", "default" => false, "help" => "Prints in uppercase letters"));
$parser->addArgument("name", array("required" => true, "help" => "The subject's name"));

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
