<?php declare(strict_types=1);

namespace Tests;

require_once "vendor/autoload.php";

use Eboubaker\JSON\Contracts\JSONStringable;
use Eboubaker\JSON\JSONFinder;
use Eboubaker\JSON\JSONObject;
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
        $this->assertEquals("f8cb82c5544ed1fb18a1a3c3eb099eaa", md5($parsed->toReadableString(2)));
    }

    /**
     * @coversNothing
     */
    public function testCanCountEntries(): void
    {
        $count = (new JSONFinder(JSONFinder::T_ALL_JSON))->findJsonEntries($this->rawHTMLResponse)->count();
        $this->assertEquals(418, $count);
    }

    /**
     * @coversNothing
     */
    public function testCanCountEntriesWithJS(): void
    {
        $count = (new JSONFinder(JSONFinder::T_ALL_JSON | JSONFinder::T_JS))->findJsonEntries($this->rawHTMLResponse)->count();
        $this->assertEquals(224, $count);
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
    public function testCanCountAllContainedEntries(): void
    {
        $parsed = (new JSONFinder)->findJsonEntries($this->rawHTMLResponse);
        $this->assertEquals(1732, $parsed->countContainedEntries());
    }

    /**
     * @coversNothing
     */
    public function testCanCountAllContainedEntriesWithJS(): void
    {
        $parsed = (new JSONFinder(JSONFinder::T_ARRAY | JSONFinder::T_OBJECT | JSONFinder::T_JS))->findJsonEntries($this->rawHTMLResponse);
        $this->assertEquals(1857, $parsed->countContainedEntries());
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
        $this->assertEquals('3eabae12402f4d558476c0f29a57abb2', md5($str));
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
        $null=7;$bool=22;$num=77;$str=217;$obj=6;$arr=61;$e_obj=16;$e_arr=12;
        //@formatter:on
        $a_obj = $e_obj + $obj;
        $a_arr = $e_arr + $arr;
        $all = $a_arr + $a_obj + $str + $num + $bool + $null;
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
        $this->assertEquals($all, $count(JSONFinder::T_ALL_JSON));
    }

    /**
     * @coversNothing
     * @testdox can do filtering with javascript flag on
     */
    public function testCanDoFilteringWithJSFlag(): void
    {
        $count = fn($types) => (new JSONFinder($types))->findJsonEntries($this->rawHTMLResponse)->count();
        //@formatter:off
        $null=1;$bool=2;$num=19;$str=158;$obj=8;$arr=19;$e_obj=16;$e_arr=1;
        //@formatter:on
        $a_obj = $e_obj + $obj;
        $a_arr = $e_arr + $arr;
        $all = $a_arr + $a_obj + $str + $num + $bool + $null;
        $this->assertEquals($null, $count(JSONFinder::T_NULL | JSONFinder::T_JS));
        $this->assertEquals($bool, $count(JSONFinder::T_BOOL | JSONFinder::T_JS));
        $this->assertEquals($num, $count(JSONFinder::T_NUMBER | JSONFinder::T_JS));
        $this->assertEquals($str, $count(JSONFinder::T_STRING | JSONFinder::T_JS));
        $this->assertEquals($arr, $count(JSONFinder::T_ARRAY | JSONFinder::T_JS));
        $this->assertEquals($obj, $count(JSONFinder::T_OBJECT | JSONFinder::T_JS));
        $this->assertEquals($e_arr, $count(JSONFinder::T_EMPTY_ARRAY | JSONFinder::T_JS));
        $this->assertEquals($e_obj, $count(JSONFinder::T_EMPTY_OBJECT | JSONFinder::T_JS));
        $this->assertEquals($a_arr, $count(JSONFinder::T_EMPTY_ARRAY | JSONFinder::T_ARRAY | JSONFinder::T_JS));
        $this->assertEquals($a_obj, $count(JSONFinder::T_EMPTY_OBJECT | JSONFinder::T_OBJECT | JSONFinder::T_JS));
        $this->assertEquals($all, $count(JSONFinder::T_ALL_JSON | JSONFinder::T_JS));
    }


    /**
     * checks if we can convert a php variable into json string.
     * @coversNothing
     */
    public function testCanEncodeObjects(): void
    {
        $obj = new JSONObject((object)[
            'a' => 'b',
            'c' => 'd',
            "e" => [
                "f" => "g",
                "h" => (object)[
                    "i" => "j",
                    "k" => [1, 2, 3e-13]
                ]
            ]
        ]);
        $this->assertEquals('{"a":"b","c":"d","e":{"f":"g","h":{"i":"j","k":[1,2,3.0E-13]}}}', strval($obj));
    }

    /**
     * @coversNothing
     * @testdox can do custom conversion with JSONStringable
     */
    public function testCanDoCustomConversionWithJSONStringable(): void
    {
        $obj = [
            "key" => new class implements JSONStringable {
                public function toJSONString()
                {
                    return '["iam","custom"]';
                }
            },
            "z" => "600"
        ];
        $this->assertEquals('{"key":["iam","custom"],"z":"600"}', strval(new JSONObject($obj)));
    }
}
