<?php

namespace CHH\Optparse\Test;

use CHH\Optparse\Parser;

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

    function testSupportsPassingFlagValueWithEqualSign()
    {
        $parser = new Parser;
        $parser->addFlag("name", array("has_value" => true));
        $parser->parse(array("--name=foo"));

        $this->assertEquals("foo", $parser["name"]);
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
        $parser->addFlag("list", array("has_value" => true), function(&$val) {
            $val = explode(',', $val);
        });

        $parser->parse(array("--list", "foo,bar,baz"));

        $this->assertEquals(array("foo", "bar", "baz"), $parser["list"]);
    }

    function testArgs()
    {
        $parser = new Parser;
        $parser->addFlag("foo", array("has_value" => true));
        $parser->addArgument("bar");
        $parser->parse(array("--foo", "bar", "baz"));

        $this->assertEquals("bar", $parser["foo"]);

        $this->assertEquals(array("baz"), $parser->args());
        $this->assertEquals("baz", $parser->arg(0));
        $this->assertEquals(null, $parser->arg(1));
    }

    function testVarArgs()
    {
        $parser = new Parser;

        $parser->addFlag("help");
        $parser->addArgument("foo");
        $parser->addArgument("bar", array("var_arg" => true));

        $parser->parse(array("--help", "foo", "bar", "baz"));

        $this->assertEquals("foo", $parser["foo"]);
        $this->assertEquals(array("bar", "baz"), $parser["bar"]);
        $this->assertTrue($parser["help"]);
    }

    function testArgumentDefaultValue()
    {
        $parser = new Parser;
        $parser->addArgument("foo", array("default" => "abc"));
        $parser->parse(array());

        $this->assertEquals("abc", $parser->arg("foo"));
    }

    /**
     * @expectedException \CHH\Optparse\ArgumentException
     */
    function testThrowsExceptionWhenRequiredArgumentIsMissing()
    {
        $parser = new Parser;
        $parser->addArgument("foo", array("required" => true));

        $parser->parse(array());
    }

    /**
     * @expectedException \CHH\Optparse\ParseException
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
        $parser->addArgument("foo");
        $parser->addArgument("bar");

        $parser->parse(array("--help", "foo", "bar", "baz"));

        $this->assertEquals("foo", $parser->get("foo"));
        $this->assertEquals("bar", $parser->get("bar"));
    }

    function testFlagVarByReference()
    {
        $bar = null;
        $baz = null;

        $obj = new \StdClass;
        $obj->foo = "";

        $parser = new Parser;
        $parser->addFlag('foo', array('var' => &$foo));
        $parser->addFlagVar("bar", $bar);
        $parser->addFlagVar("baz", $baz, array("default" => "foo"));
        $parser->addFlagVar("bab", $obj->foo);

        $parser->parse(array("--foo", "--bar", "--bab"));

        $this->assertTrue($foo);
        $this->assertTrue($bar);
        $this->assertTrue($obj->foo);
        $this->assertEquals("foo", $baz);
    }

    function testBuild()
    {
        $opts = Parser::build(function($parser) {
            $parser->addFlag("help");
        });

        $opts->parse(array("--help"));

        $this->assertTrue($opts["help"]);
    }

    function testBuildClosureBinding()
    {
        if (version_compare(PHP_VERSION, "5.4.0") < 0) {
            $this->markTestSkipped("Closure binding is only supported on PHP >= 5.4.0");
        }

        $opts = Parser::build(function() {
            $this->addFlag("help");
        });

        $opts->parse(array("--help"));

        $this->assertTrue($opts["help"]);
    }

    function testUsage()
    {
        $parser = new Parser("Hello World", "hello");

        $parser->addFlag("foo", array("alias" => "-f", "help" => "Turn on fooness"));
        $parser->addFlag("bar", array("has_value" => true));

        $parser->addArgument("baz", array("required" => true));
        $parser->addArgument("boo", array("required" => false));
        $parser->addArgument("bab", array("var_arg" => true, "help" => "Bla bla bla"));

        $this->assertEquals(
            <<<EOT
Usage: hello [--foo|-f] [--bar <bar>] <baz> [<boo>] [<bab> ...]

Hello World
EOT
            , $parser->usage()
        );

        $this->assertEquals(
<<<EOT
{$parser->usage()}

Arguments:

  baz (required)
  boo
  bab: Bla bla bla

Flags:

  -f, --foo: Turn on fooness
  --bar <bar>
EOT
            , $parser->longUsage()
        );
    }
}
