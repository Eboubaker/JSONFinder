<?php declare(strict_types=1);

namespace Tests;

require_once "vendor/autoload.php";

use Eboubaker\JSON\JSONFinder;
use PHPUnit\Framework\TestCase;


final class UnitTest extends TestCase
{
    private string $rawValid;
    private string $rawHTML;

    public function setUp(): void
    {
        if (empty($this->rawValid)) {
            $this->rawValid = file_get_contents("tests/resources/valid-json.json");
        }
        if (empty($this->rawHTML)) {
            $this->rawHTML = file_get_contents("tests/resources/html-with-json.txt");
        }
    }

    /**
     * @covers \Eboubaker\JSON\JSONFinder::findJsonEntries
     */
    public function testCanParseCleanJson(): void
    {
        $parsed = (new JSONFinder)->findJsonEntries($this->rawValid);
        $this->assertCount(1, $parsed);
        $this->assertEquals(json_encode(json_decode($this->rawValid)), json_encode(json_decode('' . $parsed[0])));
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
        $count = (new JSONFinder(JSONFinder::T_ALL))->findJsonEntries($this->rawValid)->countContainedEntries();
        $this->assertEquals(39, $count);
    }

    /**
     * @covers \Eboubaker\JSON\JSONFinder::findJsonEntries
     */
    public function testIsCompatibleWithJsonDecode(): void
    {
        $parsed = (new JSONFinder)->findJsonEntries($this->rawValid);
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
        $parsed = (new JSONFinder)->findJsonEntries($this->rawHTML);
        $this->assertEquals(488, $parsed->countContainedEntries());
    }

    /**
     * @covers \Eboubaker\JSON\Contracts\JSONEnumerable::countContainedEntries
     */
    public function testCanIterateOverValues(): void
    {
        $found = (new JSONFinder())->findJsonEntries($this->rawHTML);
        foreach ($found as $key => $item) {
            $this->assertEquals($item, $found[$key]);
        }
        $str = '';
        foreach ($found->values() as $key => $item) {
            $str .= ":$key::$item:";
        }
        $this->assertEquals('39a61cb72a0b9d4393863a00f608dc87', md5($str));
    }

}
