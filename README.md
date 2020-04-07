TextAtAnyCost
=============

Modern version of Alexey Rembish [TextAtAnyCost](https://github.com/rembish/TextAtAnyCost) package.

I removed additional (ppt, docx / odt, pdf) formats in this package: nothing special with working with OpenXML (docx / odt) files, PDF required another way and nobody wants PPT.

So, the package is for read text from the only DOC format.

All algorithms, points to bytes and offsets are original (by Alexey Rembish), but this package is ready to composer autoloading, uses namespaces and works with objects (most of) inside it.

Only requirement is a `doctrine/collections` package. 

## Usage:

Add this package to composer, make instance of `TextAtAnyCost\Doc`, read doc file and get text from it:

```php
use TextAtAnyCost\Doc;

$doc = new Doc();
$doc->read('/path/to/doc/file.doc');
$text = $doc->parse();
```

Or
```php
use TextAtAnyCost\Doc;
$text = (new Doc())->parse('/path/to/doc/file.doc');
```

Or

```php
use function TextAtAnyCost\doc2text;

$text = doc2text('/path/to/doc/file.doc');
```

## Tests

Download repository, install required packages, run tests

```shell script
vendor/bin/phpunit 
```
