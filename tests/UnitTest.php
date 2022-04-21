<?php declare(strict_types=1);

namespace Tests;

require_once "vendor/autoload.php";

use Closure;
use Eboubaker\JSON\Contracts\JSONEntry;
use Eboubaker\JSON\Contracts\JSONStringable;
use Eboubaker\JSON\JSONArray;
use Eboubaker\JSON\JSONContainer;
use Eboubaker\JSON\JSONFinder;
use Eboubaker\JSON\JSONObject;
use Eboubaker\JSON\JSONValue;
use Eboubaker\JSON\Utils;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionObject;


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

    /** does it throw InvalidArgumentException? */
    private function throws(Closure $callback): bool
    {
        try {
            $callback();
        } catch (InvalidArgumentException $e) {
            return true;
        }
        return false;
    }

    public function testCanParseCleanJson(): void
    {
        $parsed = JSONFinder::make()->findEntries($this->validJSONString);
        $this->assertCount(1, $parsed);
        $v = json_encode(json_decode('' . $parsed[0]));
        $this->assertNotEmpty($v);
        $this->assertEquals(json_encode(json_decode($this->validJSONString)), $v);
    }

    public function testFinderThrowsOnInvalidType(): void
    {
        $this->assertTrue($this->throws(fn() => JSONFinder::make(0)));
        $this->assertTrue($this->throws(fn() => JSONFinder::make(JSONFinder::T_ALL_JSON + 1)));
        $this->assertTrue($this->throws(fn() => JSONFinder::make(JSONFinder::T_JS + 1)));
        $this->assertFalse($this->throws(fn() => JSONFinder::make(JSONFinder::T_NUMBER | JSONFinder::T_JS)));
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    public function testFinderEdgeCases(): void
    {
        $finder = JSONFinder::make(JSONFinder::T_ALL_JSON | JSONFinder::T_JS);
        $this->assertEmpty($finder->findEntries(""));
        $this->assertEmpty($finder->findEntries("["));
        $this->assertEmpty($finder->findEntries("{"));
        $this->assertEquals(1, $finder->findEntries("{\"1\":1,")[0]->value());
        $this->assertEquals("1", $finder->findEntries("{\"1\":1,\t")[0]->value());

        $this->assertEquals("1", $finder->findEntries('{"1":1,}')[0]->value());
        $this->assertEquals("x", $finder->findEntries('{"x":1,,"z":2}')[0]->value());
        $this->assertEquals("x", $finder->findEntries('{"x"')[0]->value());
        $this->assertEquals("x", $finder->findEntries('{"x" ')[0]->value());
        $this->assertEquals("x", $finder->findEntries('{"x":"')[0]->value());
        $this->assertEquals("x", $finder->findEntries('{"x": ')[0]->value());
        $this->assertEquals("x", $finder->findEntries('{"x":1"z":2}')[0]->value());
        $this->assertEquals(1, $finder->findEntries('[1"one"')[0]->value());
        $this->assertEquals(1, $finder->findEntries("[1,,2]")[0]->value());
        $this->assertEquals(1, $finder->findEntries("[1,,2]")[0]->value());
        $this->assertEquals(1, $finder->findEntries("[1,")[0]->value());
        $this->assertEquals(1, $finder->findEntries("[1,\n")[0]->value());

        $this->assertEquals(1, $finder->findEntries("[1,]")[0]->value());
        $this->assertEquals(null, $finder->findEntries("[undefined]")[0][0]->value());
        $this->assertEquals('한', $finder->findEntries("\"한\"")[0]->value());
        $this->assertEquals('€', $finder->findEntries("\"€\"")[0]->value());
        $this->assertEquals('€', $finder->findEntries("\"\\u20ac\"")[0]->value());
        $this->assertEquals('£', $finder->findEntries("\"\\u00a3\"")[0]->value());
        $this->assertEquals("\x0" . "a3", $finder->findEntries("\"\\u0000a3\"")[0]->value());
        $this->assertEquals("154𝄞154", $finder->findEntries("\"154\\ud834\\udd1e154\"")[0]->value());
        $this->assertEquals("154𝄞154", $finder->findEntries("\"154\\ud834\\udd1e154\"")[0]->value());

        $r = new ReflectionObject($finder);
        $m = $r->getMethod("isAllowedEntry");
        $m->setAccessible(true);
        $sus_entry = new class implements JSONEntry {
            // @formatter:off
            public function serialize() { return null; }
            public function unserialize($data) { return null;}
            function value() { return null;}
            function __toString(): string { return '';}
            function isContainer(): bool { return false;}
            function matches(string $regex): bool { return false;}
            public function equals($other, bool $strict = false): bool { return false; }
            // @formatter:on
        };
        $this->assertFalse($m->invokeArgs($finder, [$sus_entry]));
        $this->assertEquals("\x8\f", $finder->findEntries('"\\b\\f"')[0]->value());
        $this->assertEmpty($finder->findEntries('"\\u"'));
        $this->assertEmpty($finder->findEntries('"\\&"'));
        $this->assertEmpty($finder->findEntries('"'));
        $r = new ReflectionObject($finder);
        $m = $r->getMethod("unicode_to_utf8");
        $m->setAccessible(true);
        $this->assertTrue($this->throws(fn() => $m->invokeArgs($finder, [92360820687238723])));
        $this->assertEquals(9, $finder->findEntries('.9')[0]->value());
        $this->assertEquals(.9, $finder->findEntries('-+.9')[0]->value());
        $this->assertEquals(2, $finder->findEntries('2..9')[0]->value());
        $this->assertEquals(1, $finder->findEntries('1.e9')[0]->value());

        $this->assertEquals(1000000000, $finder->findEntries('1E9.6')[0]->value());
        $this->assertEquals(9, $finder->findEntries('E9')[0]->value());
        $this->assertEquals(1, $finder->findEntries('1eE9')[0]->value());
        $this->assertEquals(4, $finder->findEntries('4E-+9')[0]->value());
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
        $this->assertEquals("f8cb82c5544ed1fb18a1a3c3eb099eaa", md5($parsed->toReadableString()));
    }


    public function testCanCountEntries(): void
    {
        $count = JSONFinder::make(JSONFinder::T_ALL_JSON)->findEntries($this->rawHTMLResponse)->count();
        $this->assertEquals(421, $count);
    }


    public function testCanCountEntriesWithJS(): void
    {
        $count = JSONFinder::make(JSONFinder::T_ALL_JSON | JSONFinder::T_JS)->findEntries($this->rawHTMLResponse)->count();
        $this->assertEquals(226, $count);
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


    /**
     * @testdox can find entries with javascript flag off
     */
    public function testCanFindEntriesWithJSFlagOFF(): void
    {
        $count = fn($types) => JSONFinder::make($types)->findEntries($this->rawHTMLResponse)->count();
        //@formatter:off
        $null=7;$bool=22;$num=80;$str=217;$obj=6;$arr=61;$e_obj=16;$e_arr=12;
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
     * @testdox can find entries with javascript flag on
     */
    public function testCanFindEntriesWithJSFlagON(): void
    {
        $count = fn($types) => JSONFinder::make($types)->findEntries($this->rawHTMLResponse)->count();
        //@formatter:off
        $null=1;$bool=2;$num=20;$str=158;$obj=9;$arr=19;$e_obj=16;$e_arr=1;
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
        $this->assertEquals('{"key":["iam","custom"],"z":"600"}', strval(JSONObject::from($obj)));
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

        $this->assertFalse(JSONArray::from([1, 2, 3])->equals(JSONArray::from([1, 2, 3, 4])));
        $this->assertFalse(JSONArray::from([[1, 2, 3, 5]])->equals(JSONArray::from([[1, 2, 3, 4]])));

    }


    public function testCanQueryValues(): void
    {
        $obj = JSONObject::from([
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
        $this->assertCount(17, $obj->getAll('**', null, true));
        $this->assertCount(17, $obj->getAll('**', null, false));

        $this->assertCount(3, $obj->getAll('*', null, true));
        $this->assertCount(3, $obj->getAll('*', null, false));
        $this->assertCount(3, $obj->getAll('**.extra', null, false));
        $this->assertCount(3, $obj->getAll('**.extra', null, true));
        $this->assertEquals('e.l.o.extra', array_keys($obj->getAll('**.extra', null, true))[0]);
        $this->assertEquals('e.l.o.p.extra', array_keys($obj->getAll('**.extra', null, true))[1]);

        $this->assertEquals('b', $obj->getAll('a')[0]->value());
        $this->assertEquals('g', $obj->getAll('e.f')[0]->value());
        $this->assertCount(2, $obj->getAll('e.*.i'));
        $this->assertFalse($obj->getAll('e..i'));
        $this->assertFalse($obj->getAll('e.**.*'));

        $this->assertCount(1, $obj->getAll('e.*.i', fn($v) => $v->value() === 'j'));
        $this->assertEquals('e.h.i', array_keys($obj->getAll('e.*.i', fn($v) => $v->value() === 'j', true))[0]);

        $this->assertEquals('j', $obj->getAll('e.*.i', fn($v) => $v->value() === 'j')[0]->value());
    }


    public function testCanMatchValues(): void
    {
        $val = new JSONValue("a very small text?");
        $this->assertTrue($val->matches("/text\\?$/"));
        $this->assertFalse($val->matches("/^text\\?/"));
        $obj = JSONObject::from(["a" => "b"]);
        $this->assertFalse($obj->matches("/b/"));
        $this->assertEquals($obj, $obj->value());
    }


    public function testCanFindValues(): void
    {
        $obj = JSONObject::from([
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
        $this->assertEquals(false, $obj->search(['m' => "mama mia"]));
        $this->assertEquals(false, $obj->search([null]));
        $this->assertEquals(false, $obj->search(["**.**.name"]));

        $this->assertEquals('s', $obj->search([
            'extra.r' => fn(JSONEntry $v) => $v->matches("/s/"),
            '*.q.**.x' => fn(JSONEntry $v) => $v->matches("/y/")
        ])['extra']['r']->value());

        $object = JSONObject::from([
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

    public function testCanCheckContainerIsContainer()
    {
        $entries = JSONFinder::make(JSONFinder::T_ALL_JSON | JSONFinder::T_JS)->findEntries($this->rawHTMLResponse);
        foreach ($entries as $entry) {
            $this->assertEquals($entry instanceof JSONContainer, $entry->isContainer());
        }
    }

    public function testWillThrowOnInvalidEntryValue()
    {
        $this->assertTrue($this->throws(function () {
            /** @noinspection PhpParamsInspection */
            new JSONValue(["foo"]);
        }));
        $this->assertTrue($this->throws(function () {
            $v = new JSONValue("foo");
            $prop = (new ReflectionObject($v))->getProperty('value');
            $prop->setAccessible(true);
            $prop->setValue($v, ["foo"]);
            new JSONValue(strval($v));
        }));
        $this->assertFalse($this->throws(function () {
            new JSONValue(new class implements JSONStringable {
                public function toJSONString(): string
                {
                    return "foo";
                }
            });
        }));
        $this->assertTrue($this->throws(function () {
            new JSONValue(new JSONArray());
        }));
        $this->assertTrue($this->throws(function () {
            JSONArray::from(["foo" => 1]);
        }));
        $this->assertTrue($this->throws(function () {
            /** @noinspection PhpParamsInspection */
            JSONArray::from("main");
        }));
        $this->assertFalse($this->throws(function () {
            $arr = new JSONArray();
            $arr[] = new JSONValue("foo");
        }));
        $this->assertTrue($this->throws(function () {
            $arr = new JSONArray();
            $arr[] = (object)["x" => 0];
        }));
        $this->assertTrue($this->throws(function () {
            new JSONArray((object)["x" => 0]);
        }));
        $this->assertTrue($this->throws(function () {
            new JSONArray([0, 2]);
        }));
        $this->assertTrue($this->throws(function () {
            /** @noinspection PhpParamsInspection */
            JSONObject::from("tail");
        }));
        $this->assertTrue($this->throws(function () {
            new JSONObject(new ReflectionObject((object)[]));
        }));
        $this->assertTrue($this->throws(function () {
            new JSONObject(2);
        }));
        $this->assertTrue($this->throws(function () {
            new JSONArray(2);
        }));
        $this->assertTrue($this->throws(function () {
            $obj = new JSONObject();
            $obj[] = (object)["x" => 0];
        }));
        $this->assertFalse($this->throws(function () {
            $obj = new JSONObject();
            $obj[] = "x";
        }));
        $this->assertFalse($this->throws(function () {
            $obj = new JSONArray();
            $obj[] = "x";
        }));
    }

    public function testCanAddEntriesWithArraySyntax()
    {
        $gen = function () {
            yield "foo";
            yield "bar";
            yield new JSONObject();
            yield (object)["zzz" => "yyy"];
            yield (object)[];
        };
        $genKeys = function () {
            yield "foo";
            yield "foobar" => "bar";
        };
        $genKeysArray = function () {
            yield "foo";
            yield "foobar" => "bar";
            yield [1] => "bar";
        };
        $arr = new JSONArray();
        $arr[] = JSONArray::from($gen());
        $this->assertEquals("foo", $arr->get('0.0')->value());

        $this->assertTrue($this->throws(function () use ($gen) {
            $arr = new JSONArray();
            $arr["xyz"] = JSONArray::from($gen());
        }));

        $obj = new JSONObject();
        $obj[] = JSONArray::from($gen());
        $this->assertEquals("foo", $obj->get('0.0')->value());


        $obj = new JSONObject();
        $obj["x"] = JSONObject::from($genKeys());
        $this->assertEquals("bar", $obj->get('x.foobar')->value());

        $this->assertTrue($this->throws(function () use ($genKeysArray) {
            JSONObject::from($genKeysArray());
        }));
    }

    public function testCanUnsetWithArraySyntax()
    {
        $array = new JSONArray();
        $array[] = JSONObject::from(["x" => "var"]);
        $this->assertCount(1, $array);
        unset($array[0]);
        $this->assertTrue($array->isEmpty());
        $this->assertCount(0, $array);
    }

    public function testCanConvertToAssociative(): void
    {
        $this->assertEquals("a5b32a759493cc9162e541f830da03df", md5(serialize(JSONFinder::make()->findEntries($this->rawHTMLResponse)->assoc())));
    }

    public function testCanGetContainers(): void
    {
        $array = JSONFinder::make()->findEntries($this->rawHTMLResponse);
        $containers = [];
        foreach ($array as $entry) {
            if ($entry->isContainer()) {
                $containers[] = $entry;
            }
        }
        self::assertEquals($containers, $array->containers());
        self::assertEquals($containers, iterator_to_array($array->containersIterator()));
    }

    public function testCanGetFirstAndLast(): void
    {
        $array = JSONArray::from([5, 6, 7]);
        $this->assertCount(3, $array);
        $this->assertEquals($array[0], $array->first());
        $this->assertEquals($array[count($array) - 1], $array->last());
    }

    public function testCanCheckIfValuePathExists(): void
    {
        $object = JSONObject::from([
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
        $this->assertTrue($object->has('**'));
        $this->assertTrue($object->has('*'));
        $this->assertFalse($object->has('response.**.*'));

        $this->assertTrue($object->has('response.posts.0.id'));
        $this->assertTrue($object->has('response.posts.*.id'));
        $this->assertTrue($object->has('response.posts.**.replies'));

        $this->assertFalse($object->has('response.posts.comments.0.replies'));
        $this->assertFalse($object->has('response.**.videos.**.posts'));
        $this->assertFalse($object->has('response.posts.comments.0.replies'));
    }

    public function testCanGetSingleValueWithPath(): void
    {
        $object = JSONObject::from([
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
        $this->assertEquals('a5339be0849ced1ffe', $object->get('**')->value());
        $this->assertEquals('a5339be0849ced1ffe', $object->get('*')['hash']->value());

        $this->assertEquals('1634', $object->get('response.posts.0.id')->value());
        $this->assertEquals('1634', $object->get('response.posts.*.id')->value());
        $this->assertCount(0, $object->get('response.posts.**.replies')->value());
        $this->assertEquals(2, $object->get('response.posts.comments.0.replies', fn() => 2));
        $this->assertEquals(2, $object->get('response.posts.comments.0.replies', 2));

        $this->assertNull($object->get('response.**.*'));

        $this->assertNull($object->get('response.posts.comments.0.replies'));
        $this->assertNull($object->get('response.**.videos.**.posts'));
        $this->assertNull($object->get('response.posts.comments.0.replies'));
    }

    public function testTypeOf(): void
    {
        $this->assertEquals('string("12")', Utils::typeof("12"));
        $this->assertEquals('boolean("true")', Utils::typeof(true));
        $this->assertEquals('boolean("false")', Utils::typeof(false));
        $this->assertEquals('string("")', Utils::typeof(''));
        $this->assertEquals('double(3.4)', Utils::typeof(3.4));
        $this->assertEquals('integer(0)', Utils::typeof(0));
        $this->assertEquals('integer(1)', Utils::typeof(1));
    }
}
