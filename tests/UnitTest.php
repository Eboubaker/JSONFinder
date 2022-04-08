<?php declare(strict_types=1);

namespace Tests;

require_once "vendor/autoload.php";

use Eboubaker\JSON\JSONFinder;
use PHPUnit\Framework\TestCase;


final class UnitTest extends TestCase
{
    private string $validJSONString;
    private string $rawHTMLResponse;

    public function setUp(): void
    {
        if (empty($this->validJSONString)) {
            $this->validJSONString = file_get_contents("tests/resources/valid-json.json");
        }
        if (empty($this->rawHTMLResponse)) {
            $this->rawHTMLResponse = file_get_contents("tests/resources/document.html.txt");
        }
    }

    /**
     * @coversNothing
     */
    public function testCanParseCleanJson(): void
    {
        $parsed = (new JSONFinder)->findJsonEntries($this->validJSONString);
        $this->assertCount(1, $parsed);
        $v = json_encode(json_decode('' . $parsed[0]));
        $this->assertNotEmpty($v);
        $this->assertEquals(json_encode(json_decode($this->validJSONString)), $v);
    }

    /**
     * @coversNothing
     */
    public function testReadableStringIsCompatibleWithJsonDecode(): void
    {
        $parsed = (new JSONFinder)->findJsonEntries($this->validJSONString);
        $v = json_encode(json_decode($parsed[0]->toReadableString(2)));
        $this->assertNotEmpty($v);
        $this->assertEquals(json_encode(json_decode($this->validJSONString)), $v);

        $parsed = (new JSONFinder)->findJsonEntries($this->rawHTMLResponse);
        foreach ($parsed->entries() as $entry) {
            $decoded = json_decode($entry->toReadableString(2));
            $this->assertEquals(JSON_ERROR_NONE, json_last_error());
            $this->assertEquals(json_encode(json_decode(strval($entry))), json_encode($decoded));
        }
    }

    /**
     * @coversNothing
     */
    public function testToReadableStringDidNotChangeOutCome(): void
    {
        $parsed = (new JSONFinder)->findJsonEntries($this->rawHTMLResponse);
        $this->assertEquals("567ce8bd75a13b9c8277f0c0bf526c94", md5($parsed->toReadableString(2)));
    }

    /**
     * @coversNothing
     */
    public function testCanCountEntries(): void
    {
        $count = (new JSONFinder(JSONFinder::T_ALL))->findJsonEntries($this->validJSONString)->countContainedEntries();
        $this->assertEquals(39, $count);
    }

    /**
     * @coversNothing
     */
    public function testIsCompatibleWithJsonDecode(): void
    {
        $parsed = (new JSONFinder)->findJsonEntries($this->validJSONString);
        foreach ($parsed as $item) {
            json_decode(strval($item));
            $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        }
    }

    /**
     * @coversNothing
     */
    public function testCanFindJsonInHtmlResponse(): void
    {
        $parsed = (new JSONFinder)->findJsonEntries($this->rawHTMLResponse);
        $this->assertEquals(1176, $parsed->countContainedEntries());
    }

    /**
     * @coversNothing
     */
    public function testCanIterateOverValuesAndAccessWithArraySyntax(): void
    {
        $found = (new JSONFinder())->findJsonEntries($this->rawHTMLResponse);
        $str = '';
        foreach ($found->values() as $key => $item) {
            $str .= ":$key::$item:";
        }
        $this->assertEquals('20fb1ea35afd63259651a22d3c836b99', md5($str));
        foreach ($found as $key => $item) {
            /** @noinspection PhpArrayAccessCanBeReplacedWithForeachValueInspection */
            $this->assertEquals($item, $found[$key]);
        }
    }

    /**
     * @coversNothing
     */
    public function testCanDoFiltering(): void
    {
        $count = fn($types) => (new JSONFinder($types))->findJsonEntries($this->rawHTMLResponse)->count();
        //@formatter:off
        $null=6;$bool=29;$num=320;$str=1170;$obj=72;$arr=94;$e_obj=28;$e_arr=65;
        $a_obj = $e_obj + $obj;
        $a_arr = $e_arr + $arr;
        $all = $a_arr + $a_obj + $str + $num + $bool + $null;
        //@formatter:on
        $this->assertEquals($null, $count(JSONFinder::T_NULL));
        $this->assertEquals($bool, $count(JSONFinder::T_BOOL));
        $this->assertEquals($num, $count(JSONFinder::T_NUMBER));
        $this->assertEquals($str, $count(JSONFinder::T_STRING));
        $this->assertEquals($arr, $count(JSONFinder::T_ARRAY));
        $this->assertEquals($obj, $count(JSONFinder::T_OBJECT));
        $this->assertEquals($e_arr, $count(JSONFinder::T_EMPTY_ARRAY));
        $this->assertEquals($e_obj, $count(JSONFinder::T_EMPTY_OBJECT));
        $this->assertEquals($a_arr, $count(JSONFinder::T_EMPTY_ARRAY | JSONFinder::T_ARRAY));
        $this->assertEquals($a_obj, $count(JSONFinder::T_EMPTY_OBJECT | JSONFinder::T_OBJECT));
        $this->assertEquals($all, $count(JSONFinder::T_ALL));
    }
}
