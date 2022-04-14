<?php declare(strict_types=1);

namespace Tests;

require_once "vendor/autoload.php";

use Eboubaker\JSON\Contracts\JSONEntry;
use Eboubaker\JSON\Contracts\JSONStringable;
use Eboubaker\JSON\JSONFinder;
use Eboubaker\JSON\JSONObject;
use Eboubaker\JSON\JSONValue;
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


    public function testCanParseCleanJson(): void
    {
        $parsed = JSONFinder::make()->findEntries($this->validJSONString);
        $this->assertCount(1, $parsed);
        $v = json_encode(json_decode('' . $parsed[0]));
        $this->assertNotEmpty($v);
        $this->assertEquals(json_encode(json_decode($this->validJSONString)), $v);
    }


    public function testReadableStringIsCompatibleWithJsonDecode(): void
    {
        $parsed = JSONFinder::make()->findEntries($this->validJSONString);
        $v = json_encode(json_decode($parsed[0]->toReadableString(2)));
        $this->assertNotEmpty($v);
        $this->assertEquals(json_encode(json_decode($this->validJSONString)), $v);

        $parsed = JSONFinder::make()->findEntries($this->rawHTMLResponse);
        foreach ($parsed->entries() as $entry) {
            $decoded = json_decode($entry->toReadableString(2));
            $this->assertEquals(JSON_ERROR_NONE, json_last_error());
            $this->assertEquals(json_encode(json_decode(strval($entry))), json_encode($decoded));
        }
    }


    public function testToReadableStringDidNotChangeOutCome(): void
    {
        $parsed = JSONFinder::make()->findEntries($this->rawHTMLResponse);
        $this->assertEquals("f8cb82c5544ed1fb18a1a3c3eb099eaa", md5($parsed->toReadableString(2)));
    }


    public function testCanCountEntries(): void
    {
        $count = JSONFinder::make(JSONFinder::T_ALL_JSON)->findEntries($this->rawHTMLResponse)->count();
        $this->assertEquals(420, $count);
    }


    public function testCanCountEntriesWithJS(): void
    {
        $count = JSONFinder::make(JSONFinder::T_ALL_JSON | JSONFinder::T_JS)->findEntries($this->rawHTMLResponse)->count();
        $this->assertEquals(225, $count);
    }


    public function testIsCompatibleWithJsonDecode(): void
    {
        $parsed = JSONFinder::make()->findEntries($this->validJSONString);
        foreach ($parsed as $item) {
            json_decode(strval($item));
            $this->assertEquals(JSON_ERROR_NONE, json_last_error());
        }
    }


    public function testCanCountAllContainedEntries(): void
    {
        $parsed = JSONFinder::make()->findEntries($this->rawHTMLResponse);
        $this->assertEquals(1732, $parsed->countAll());
    }


    public function testCanCountAllContainedEntriesWithJS(): void
    {
        $parsed = JSONFinder::make(JSONFinder::T_ARRAY | JSONFinder::T_OBJECT | JSONFinder::T_JS)->findEntries($this->rawHTMLResponse);
        $this->assertEquals(1858, $parsed->countAll());
    }


    public function testCanIterateOverValuesAndAccessWithArraySyntax(): void
    {
        $found = JSONFinder::make()->findEntries($this->rawHTMLResponse);
        $str = '';
        foreach ($found->values() as $key => $item) {
            $item = $item->value();
            $str .= ":$key::$item:";
        }
        $this->assertEquals('f23f35c0a64b7e0a476419e6b45e2cb8', md5($str));
        foreach ($found as $key => $item) {
            $this->assertEquals($item, $found[$key]);
        }
    }


    public function testCanDoFiltering(): void
    {
        $count = fn($types) => JSONFinder::make($types)->findEntries($this->rawHTMLResponse)->count();
        //@formatter:off
        $null=7;$bool=22;$num=79;$str=217;$obj=6;$arr=61;$e_obj=16;$e_arr=12;
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
     * @testdox can do filtering with javascript flag on
     */
    public function testCanDoFilteringWithJSFlag(): void
    {
        $count = fn($types) => JSONFinder::make($types)->findEntries($this->rawHTMLResponse)->count();
        //@formatter:off
        $null=1;$bool=2;$num=19;$str=158;$obj=9;$arr=19;$e_obj=16;$e_arr=1;
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
     * @testdox can do custom conversion with JSONStringable
     */
    public function testCanDoCustomConversionWithJSONStringable(): void
    {
        $obj = [
            "key" => new class implements JSONStringable {
                public function toJSONString(): string
                {
                    return '["iam","custom"]';
                }
            },
            "z" => "600"
        ];
        $this->assertEquals('{"key":["iam","custom"],"z":"600"}', strval(new JSONObject($obj)));
    }

    /**
     * @testdox can do equality on JSONValues
     */
    public function testCanDoEqualityOnJSONValues(): void
    {
        $v = new JSONValue(5);
        $this->assertTrue($v->equals(new JSONValue(5)));
        $this->assertTrue($v->equals(5));

        $v = new JSONValue(true);
        $this->assertTrue($v->equals(1));
        $this->assertTrue($v->equals(2));
        $this->assertTrue($v->equals(-2));
        $this->assertFalse($v->equals(1, true));

        $o1 = new class implements JSONStringable {
            public function toJSONString(): string
            {
                return '';
            }
        };
        $o2 = new class implements JSONStringable {
            public function toJSONString(): string
            {
                return '';
            }
        };
        $v = new JSONValue($o1);
        $this->assertTrue($v->equals(new JSONValue($o1)));
        $this->assertTrue($v->equals($o1));
        $this->assertTrue($v->equals($o1, true));
        $this->assertFalse($v->equals($o2));
        $this->assertFalse($v->equals(new JSONValue($o2)));
        $this->assertFalse($v->equals($o2, true));
    }


    public function testCanQueryValues(): void
    {
        $obj = new JSONObject([
            'a' => 'b',
            'c' => 'd',
            "e" => [
                "f" => "g",
                "h" => [
                    "i" => "j",
                    "k" => [1, 2, 3e-13]
                ],
                "l" => [
                    "i" => 1,
                    "m" => "n",
                    "o" => [
                        "extra" => [
                            "r" => "m"
                        ],
                        "p" => [
                            "extra" => [
                                "r" => "s"
                            ],
                            "M" => [
                                "q" => [
                                    "extra" => [
                                        "r" => "s",
                                        "t" => [
                                            "u" => "v",
                                            "w" => [
                                                "x" => "y"
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        "r" => [1, 5, 3e-13]
                    ]
                ]
            ]
        ]);
        $this->assertEquals('b', array_values($obj->getAll('a'))[0]->value());
        $this->assertEquals('g', array_values($obj->getAll('e.f'))[0]->value());
        $this->assertCount(2, $obj->getAll('e.*.i'));
        $this->assertCount(1, $obj->getAll('e.*.i', fn($v) => $v->value() === 'j'));
        $this->assertEquals('j', array_values($obj->getAll('e.*.i', fn($v) => $v->value() === 'j'))[0]->value());
    }


    public function testCanMatchValues(): void
    {
        $val = new JSONValue("a very small text?");
        $this->assertTrue($val->matches("/text\\?$/"));
        $this->assertFalse($val->matches("/^text\\?/"));
        $obj = new JSONObject(["a" => "b"]);
        $this->assertFalse($obj->matches("/b/"));
    }


    public function testCanFindValues(): void
    {
        $obj = new JSONObject([
            'a' => 'b',
            'c' => 'd',
            "e" => [
                "f" => "g",
                "h" => [
                    "i" => "j",
                    "k" => [1, 2, 3e-13]
                ],
                "l" => [
                    "i" => 1,
                    "m" => "n",
                    "extra" => [
                        "r" => [
                            "o" => "n"
                        ]
                    ],
                    "o" => [
                        "extra" => [
                            "r" => "m"
                        ],
                        "p" => [
                            "extra" => [
                                "r" => "s"
                            ],
                            "M" => [
                                "q" => [
                                    "extra" => [
                                        "r" => "s",
                                        "t" => [
                                            "u" => "v",
                                            "w" => [
                                                "x" => "y"
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        "r" => [1, 5, 3e-13]
                    ]
                ]
            ]
        ]);
        $this->assertEquals('s', $obj->search([
            'extra.r' => fn(JSONEntry $v) => $v->matches("/s/"),
            '*.q.**.x' => fn(JSONEntry $v) => $v->matches("/y/")
        ])['extra']['r']->value());

        $object = new JSONObject([
            "response" => [
                "hash" => "a5339be0849ced1ffe",
                "posts" => [
                    [
                        "id" => "1634",
                        "likes" => 700,
                        "text" => "Machine learning for beginners",
                    ],
                    [
                        "id" => "1234",
                        "likes" => 200,
                        "text" => "top 10 best movies of 2019",
                        "comments" => [
                            [
                                "id" => "1134",
                                "likes" => 2,
                                "replies" => [],
                                "content" => "thanks for sharing",
                            ],
                            [
                                "id" => "1334",
                                "content" => "this video is bad",
                                "likes" => 0,
                                "replies" => [
                                    [
                                        "id" => "1435",
                                        "likes" => 0,
                                        "content" => "this is not true",
                                    ],
                                    [
                                        "id" => "1475",
                                        "likes" => 0,
                                        "content" => "agree this is the worst",
                                    ]
                                ],
                            ]
                        ],
                    ]
                ]
            ]
        ]);

        // get first object which matches these paths/filters.
        $comment_with_likes = $object->search([
            "content",
            "likes" => fn(JSONEntry $v) => $v->value() > 0
        ]);
        $this->assertEquals('{"id":"1134","likes":2,"replies":[],"content":"thanks for sharing"}', strval($comment_with_likes));

        $post_with_comment_replies = $object->search([
            "comments.*.replies"
        ]);

        $this->assertEquals("1234", $post_with_comment_replies['id']->value());

        $comment_with_replies = $object->search([
            "replies.*"
        ]);

        $this->assertEquals("1334", $comment_with_replies['id']->value());

        $comment_with_bad_words = $object->search([
            "content" => fn(JSONEntry $v) => $v->matches('/worst|bad/')
        ]);

        $this->assertEquals("1334", $comment_with_bad_words['id']->value());
    }

    /**
     * @testdox can decode utf8
     */
    public function testCanDecodeUtf8()
    {
        $str = '{"language":{"name":"الإنجليزية","flag":"🏴"}}';
        $this->assertEquals($str, strval(JSONFinder::make()->findEntries($str)[0]));
        $str = '{"char":"\u0645"}}';
        $this->assertEquals('{"char":"م"}', strval(JSONFinder::make()->findEntries($str)[0]));
    }

    public function testCanSerializeEntries()
    {
        $entries = JSONFinder::make()->findEntries($this->validJSONString);
        $serialized = serialize($entries);
        $this->assertEquals(strval($entries), strval(unserialize($serialized)));
        $this->assertTrue(unserialize($serialized)->equals($entries));
    }

    public function testCanCheckEqualityOfContainers()
    {
        $entries = JSONFinder::make(JSONFinder::T_ALL_JSON | JSONFinder::T_JS)->findEntries($this->rawHTMLResponse);
        foreach ($entries as $entry) {
            $this->assertTrue($entry->equals(unserialize(serialize($entry))));
        }
    }
}
