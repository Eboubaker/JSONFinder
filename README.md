# JSONFinder
JSONFinder is a library that can find json values in a mixed text. 

## Installation

install the library with composer
```
composer require eboubaker/json-finder
```

## Quick Start
suppose you want to extract all json from an http response (from &lt;script&gt; tags).
```php
use Eboubaker\JSON\JSONFinder;

$html = file_get_contents('http://www.google.com');
$finder = new JSONFinder();

/**
 * @var JSONArray $foundEntries
 */
$foundEntries = $finder->findJsonEntries($html);

// associative array of all found json entries
$associative = $foundEntries->assoc();

// first entry in the JSONArray
$first = $json[0];

// get json string of the first entry (similar to json_encode)
$jsonString = strval($first);

// loop through entries of the JSONArray ($foundEntries)
foreach($foundEntries as $key => $value) {
    // ....
}

// loop through every deeply nested value(primitive values)
foreach($foundEntries->values() as $key => $value) {
    // ....
}
```
All other functions are self documented.
