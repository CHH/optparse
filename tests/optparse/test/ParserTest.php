<?php

namespace optparse\test;

use optparse\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    function testFlags()
    {
        $parser = new Parser;

        $parser->addFlag("help", array("alias" => "-h", "default" => false));

        $parser->parse(array("-h"));

        $this->assertTrue($parser["help"]);
        $this->assertEmpty($parser->args());
    }

    function testFlagsAfterArgumentsParseCorrectly()
    {
        $parser = new Parser;

        $parser->addFlag("help");
        $parser->parse(array("foo", "bar", "--help"));

        $this->assertEquals(array("foo", "bar"), $parser->args());
        $this->assertTrue($parser["help"]);
    }

    function testCallbacks()
    {
        $parser = new Parser;
        $parser->addFlag("list", array("has_value" => true, "default" => array()), function($val) {
            return explode(',', $val);
        });

        $parser->parse(array("--list", "foo,bar,baz"));

        $this->assertEquals(array("foo", "bar", "baz"), $parser["list"]);
    }

    function testArgs()
    {
        $parser = new Parser;
        $parser->addFlag("foo", array("has_value" => true));

        $parser->parse(array("--foo", "bar", "baz"));

        $this->assertEquals("bar", $parser["foo"]);

        $this->assertEquals(array("baz"), $parser->args());
        $this->assertEquals("baz", $parser->get(0));
        $this->assertEquals(null, $parser->get(1));
    }

    /**
     * @expectedException \optparse\ParseException
     */
    function testExceptionOnUndefinedArgument()
    {
        $parser = new Parser;

        $parser->parse(array("--foo", '-h'));
    }

    function testDefinedArgs()
    {
        $parser = new Parser;

        $parser->addFlag("help");
        $parser->addArgument("foo", array("count" => 2));
        $parser->addArgument("bar");

        $parser->parse(array("--help", "foo", "bar", "baz"));

        $this->assertEquals(array("foo", "bar"), $parser->get("foo"));
        $this->assertEquals("baz", $parser->get("bar"));
    }

    function testUsage()
    {
        $parser = new Parser("Hello World");

        $parser->addFlag("foo", array("alias" => "-f"));
        $parser->addFlag("bar", array("has_value" => true));

        $parser->addArgument("baz", array("required" => true));
        $parser->addArgument("boo", array("required" => false));

        $this->assertEquals(
            <<<EOT
Usage: [--foo|-f] [--bar <bar>] <baz> [<boo>]

Hello World
EOT
            , $parser->usage()
        );
    }
}
