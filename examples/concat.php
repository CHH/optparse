<?php

require __DIR__ . "/../vendor/autoload.php";

use CHH\Optparse;

$opts = new Optparse\Parser;

$opts->addFlag("help", array("alias" => "-h"), function() use ($opts) {
    echo "{$opts->usage()}\n";
    exit(0);
});

$opts->addArgument("files", array("var_arg" => true, "required" => true));

try {
    $opts->parse();
} catch (Optparse\Exception $e) {
    fwrite(STDERR, "{$opts->usage()}\n");
    exit(1);
}

foreach ($opts["files"] as $file) {
    readfile($file);
    echo "\n";
}

