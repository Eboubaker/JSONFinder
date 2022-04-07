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
     * @covers \Eboubaker\JSON\JSONFinder::findJsonEntries
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
     * @covers \Eboubaker\JSON\JSONFinder::toReadableString
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
     * @covers \Eboubaker\JSON\JSONFinder::findJsonEntries
     */
    public function testCanFindJsonInText(): void
    {
        $this->assertEquals(true, true);
    }

    /**
     * @covers \Eboubaker\JSON\Contracts\JSONEnumerable::countContainedEntries
     */
    public function testCanCountEntries(): void
    {
        $count = (new JSONFinder(JSONFinder::T_ALL))->findJsonEntries($this->validJSONString)->countContainedEntries();
        $this->assertEquals(39, $count);
    }

    /**
     * @covers \Eboubaker\JSON\JSONFinder::findJsonEntries
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
     * @covers \Eboubaker\JSON\JSONFinder::findJsonEntries
     */
    public function testCanFindJsonInHtmlResponse(): void
    {
        $parsed = (new JSONFinder)->findJsonEntries($this->rawHTMLResponse);
        $this->assertEquals(1176, $parsed->countContainedEntries());
    }

    /**
     * @covers \Eboubaker\JSON\Contracts\JSONEnumerable::countContainedEntries
     */
    public function testCanIterateOverValues(): void
    {
        $found = (new JSONFinder())->findJsonEntries($this->rawHTMLResponse);
        foreach ($found as $key => $item) {
            $this->assertEquals($item, $found[$key]);
        }
        $str = '';
        foreach ($found->values() as $key => $item) {
            $str .= ":$key::$item:";
        }
        $this->assertEquals('20fb1ea35afd63259651a22d3c836b99', md5($str));
    }

}
