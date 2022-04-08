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
            $this->assertEquals($item, $found[$key]);
        }
    }

}
