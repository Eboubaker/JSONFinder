# JSONFinder
a library that can find json values in a mixed text or html documents, can filter and search the json tree, and converts php objects to json without 'ext-json' extension.

[![Latest Stable Version](https://img.shields.io/packagist/v/eboubaker/json-finder.svg?style=flat-square)](https://packagist.org/packages/eboubaker/json-finder)
[![PHP Version Require](http://poser.pugx.org/eboubaker/json-finder/require/php)](https://packagist.org/packages/eboubaker/json-finder)
[![CI Status](https://github.com/eboubaker/JSONFinder/actions/workflows/CI.yml/badge.svg)](https://github.com/Eboubaker/JSONFinder/actions)
[![Tests Coverage](https://github.com/Eboubaker/JSONFinder/blob/main/coverage_badge.svg)](https://github.com/Eboubaker/JSONFinder/actions)

## Installation

install the library with composer
```
composer require eboubaker/json-finder
```

## Quick Start
suppose you want to scrap all json from an http response (from &lt;script&gt; tags).
```php
use Eboubaker\JSON\JSONFinder;

$html = file_get_contents('http://www.youtube.com');
$finder = JSONFinder::make();

/**
 * @var \Eboubaker\JSON\JSONArray $foundEntries
 */
$foundEntries = $finder->findEntries($html);

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
            "k" => [1, 2, 3e-13, ["x"=> 0.3]]
        ]
    ]
];
$obj = JSONObject::from($phpvalue);
echo strval($obj);// '{"a":"b","e":{"f":null,"h":{"i":"j","k":[1,2,3.0E-13,{"x":0.3}]}}}'
```

### JSON Query

you can search for values inside the json tree. with dot notation path and wildcards "`*`" "`**`".

| Path              | Meaning     |
| :---              | :----       |
| `video.formats.*` | every entry inside `video.formats` (3 results)       |
| `video.**`        | Every deeply nested value inside `video` ("12345","mp4","https://<span></span>example.com/video720.mp4",..., "1280x720") (10 results)       |
| `video.**.url`        | Every deeply nested value inside `video` with key `url` ("https://<span></span>example.com/video720.mp4",...) (3 results)       |

------------------------------------------

```php
use Eboubaker\JSON\JSONObject;

$obj = JSONObject::from([
    "meta" => [
        "id" => "12345",
        "title" => "My Title",
    ],
    "video" => [
        "id" => "12345",
        "formats" => [
            [
                "name" => "mp4",
                "url" => "https://example.com/video720.mp4",
                "resolution" => "1280x720",
            ],
            [
                "name" => "mp4",
                "url" => "https://example.com/video1080.mp4",
                "resolution" => "1920x1080",
            ],
            [
                "name" => "webm",
                "url" => "https://example.com/video720.webm",
                "resolution" => "1280x720",
            ],
        ]
    ]
]);

$obj->getAll('formats'); // empty array [], $obj does not contain 'formats' path

$result = $obj->getAll('meta.id'); // [0 => JSONValue("12345")]
$video_id = $result[0];

// get all deep entries that contains an 'id' key
$has_id = $obj->getAll('**.id');

// get all formats in 'video.formats' path
$all_formats = $obj->getAll('video.formats.*');

// you can apply a filter to the results
$mp4_formats = $obj->getAll('video.formats.*', fn($v) => $v->get('name')->equals('mp4')); // ['video.formats.0' => JSONObject({"name":"mp4","url":"https://example.com/video720.mp4","resolution":"1280x720"})]


// return paths of found results as the keys in the result array
$has_id = $obj->getAll('**.id', null, true); // ['meta.id' => JSONValue("12345"), 'video.id' => JSONValue("12345")]
```

### Find JSON object/array

You can find a single json object/array which contains specific keys using `JSONObject::search()`
or `JSONArray::search()`.  
the method accept a list of paths with optional value filter.  
will returns the target that contains all the provided paths.

```php
use Eboubaker\JSON\JSONObject;
use Eboubaker\JSON\Contracts\JSONEntry;

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

// get first object which matches these paths and filters.
$comment_with_likes = $object->search([
    "content",
    "likes" => fn(JSONEntry $v) => $v->value() > 0
]);
echo $comment_with_likes;// {"id":"1134","likes":2,"replies":[],"content":"thanks for sharing"}


$post_with_comment_replies = $object->search([
    "comments.*.replies"
]);
echo $post_with_comment_replies['id'];// "1234"


// more than 0 replies
$comment_with_replies = $object->search([
    "replies.*"
]);
echo $comment_with_replies['id'];// "1334"


$comment_with_bad_words = $object->search([
    "content" => fn(JSONEntry $v) => $v->matches('/worst|bad/')
]);

echo $comment_with_bad_words['id'];// "1334"
```

### Controlling results of JSONFinder

you can add flags to the JSONFinder factory to set the allowed types of values that the JSONFinder will return.  
for example if you want to also include javascript objects in the resutls you can add the T_JS flag. this will also
match javascript object-keys or javascript strings that are quoted with single quote `'`

```php
use Eboubaker\JSON\JSONFinder;

$finder = JSONFinder::make(JSONFinder::T_ALL_JSON | JSONFinder::T_JS);
$finder->findEntries("{jskey: 1}");// [JSONObject({"jskey":1})]
$finder->findEntries("{'jskey': 'jsstring'}");// [JSONObject({"jskey":"jsstring"})]
```

All other functions are self documented with PHPDoc.
