# JSONFinder
JSONFinder is a library that can find json values in a mixed text. 

[![Latest Stable Version](https://img.shields.io/packagist/v/eboubaker/json-finder.svg?style=flat-square)](https://packagist.org/packages/eboubaker/json-finder)
[![PHP Version Require](http://poser.pugx.org/eboubaker/json-finder/require/php)](https://packagist.org/packages/phpunit/phpunit)
[![CI Status](https://github.com/eboubaker/JSONFinder/actions/workflows/RunTests.yml/badge.svg)](https://github.com/Eboubaker/JSONFinder/actions)

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

// first entry in the JSONArray
$first = $foundEntries[3];

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
