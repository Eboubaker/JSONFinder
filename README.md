# JSONFinder
JSONFinder is a library that can find json values in a mixed text, and converts values to json without 'ext-json' extension. 

[![Latest Stable Version](https://img.shields.io/packagist/v/eboubaker/json-finder.svg?style=flat-square)](https://packagist.org/packages/eboubaker/json-finder)
[![PHP Version Require](http://poser.pugx.org/eboubaker/json-finder/require/php)](https://packagist.org/packages/eboubaker/json-finder)
[![CI Status](https://github.com/eboubaker/JSONFinder/actions/workflows/CI.yml/badge.svg)](https://github.com/Eboubaker/JSONFinder/actions)

## Installation

install the library with composer
```
composer require eboubaker/json-finder
```

## Quick Start
suppose you want to extract all json from an http response (from &lt;script&gt; tags).
```php
use Eboubaker\JSON\JSONFinder;

$html = file_get_contents('http://www.youtube.com');
$finder = new JSONFinder();

/**
 * @var \Eboubaker\JSON\JSONArray $foundEntries
 */
$foundEntries = $finder->findJsonEntries($html);

// associative array of all found json entries
$associative = $foundEntries->assoc();

// 4th entry in the JSONArray
$entry = $foundEntries[3];

// get json string of the first entry (similar to json_encode)
$jsonString = strval($entry);

// loop through entries of the JSONArray ($foundEntries)
foreach($foundEntries as $key => $value) {
    // ....
}

// loop through every deeply nested value(not object or array)
foreach($foundEntries->values() as $keyPath => $value) {
    // ....
}

// pretty print the json entry with indentation of 2 spaces
echo $foundEntries->toReadableString(2);
```

### Value Encoding

you can encode php values into json string without ext-json.

```php
use Eboubaker\JSON\JSONObject;

$phpvalue = [
            "a" => "b",
            "e" => [
                "f" => null,
                "h" => [
                    "i" => "j",
                    "k" => [1, 2, 3e-13, ["x": 0.3]]
                ]
            ]
        ];
$obj = new JSONObject($phpvalue);
echo strval($obj);// '{"a":"b","e":{"f":null,"h":{"i":"j","k":[1,2,3.0E-13,{"x":0.3}]}}}'
```

### JSON Query

you can search for values inside the json entries. with dot notation and wildcard "`*`" "`**`" search.

```php
$obj = new JSONObject([
    "meta" => [
        "id" => "12345",
        "title" => "My Title",
    ],
    "video" => [
        "id" => "12345",
        "formats" => [
            [
                "name": "mp4",
                "url": "https://example.com/video720.mp4",
                "resolution": "1280x720",
            ],
            [
                "name": "mp4",
                "url": "https://example.com/video1080.mp4",
                "resolution": "1920x1080",
            ],
            [
                "name": "webm",
                "url": "https://example.com/video720.webm",
                "resolution": "1280x720",
            ],
        ]
    ]
]);

$result = $obj->get('meta.id'); // ['meta.id' => JSONValue("12345")]
$vide_id = array_values($result)[0];

// get all deep entries that contains a 'name' key
$has_id = $obj->get('**.id');

// get all formats in 'video.formats' path
$all_formats = $obj->get('video.formats.*');

// you can apply a filter to the results
$mp4_formats = $obj->get('video.formats.*', fn($v) => $v->get('name')->equals('mp4')); // ['video.formats.0' => JSONObject({"name":"mp4","url":"https://example.com/video720.mp4","resolution":"1280x720"})]

```

### Find JSON object/array

You can find a json object/array which contains a specific keys and values using `JSONObject::find()`
or `JSONArray::find()`.  
the method accept a list of paths with optional value filter.  
the provided paths must exist on the target and match the provided filters if they exist.

```php
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
                        "replies" : [],
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

// get first object which matches these paths and filters.
$comment_with_likes = $object->find([
    "content",
    "likes" => fn(JSONEntry $v) => $v->value() > 0
]);
echo $comment_with_likes;// {"id":"1134","likes":2,"replies":[],"content":"thanks for sharing"}


$post_with_comment_replies = $object->find([
    "comments.*.replies"
]);
echo $post_with_comment_replies['id'];// "1234"


// more than 0 replies
$comment_with_replies = $object->find([
    "replies.*"
]);
echo $comment_with_replies['id'];// "1334"


$comment_with_bad_words = $object->find([
    "content" => fn(JSONEntry $v) => $v->matches('/worst|bad/')
]);

echo $comment_with_bad_words['id'];// "1334"
```

### Controlling results of JSONFinder

you can add flags to the JSONFinder constructor to set the allowed types of values that the JSONFinder will return.  
for example if you want to also include javascript object in the resutls you can add the T_JS flag. this will also match
javascript object-keys or javascript strings that are quoted with single quote `'`

```php
$finder = new JSONFinder(JSONFinder::T_ALL_JSON | JSONFinder::T_JS);
$finder->findJsonEntries("{mykey: 1}");// [JSONObject({"mykey":1})]
$finder->findJsonEntries("{'stringkey': 'stringvalue'}");// [JSONObject({"stringkey":"stringvalue"})]
```

All other functions are self documented with PHPDoc.
