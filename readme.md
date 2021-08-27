# Reflection Field for Symphony CMS

A field for [Symphony CMS][ext-Symphony-cms] generates values based on other fields from the same entry using XPath and XSLT.

-   [Installation](#installation)
    -   [With Git and Composer](#with-git-and-composer)
    -   [With Orchestra](#with-orchestra)
-   [Basic Usage](#basic-usage)
-   [About](#about)
    -   [Requirements](#dependencies)
    -   [Dependencies](#dependencies)
-   [Support](#support)
-   [Contributing](#contributing)
-   [License](#license)

## Installation

This is an extension for [Symphony CMS][ext-Symphony-cms]. Add it to the `/extensions` folder of your Symphony CMS installation, then enable it though the interface.

### With Git and Composer

```bash
$ git clone --depth 1 https://github.com/pointybeard/symext-reflectionfield.git extensions/reflectionfield
$ composer update -vv --profile -d ./extensions/reflectionfield
```
After finishing the steps above, enable "Field: Reflection" though the administration interface or, if using [Orchestra][ext-Orchestra], with `bin/extension enable reflectionfield`.

### With Orchestra

1. Add the following extension defintion to your `.orchestra/build.json` file in the `"extensions"` block:

```json
{
    "name": "reflectionfield",
    "repository": {
        "url": "https://github.com/pointybeard/symext-reflectionfield.git"
    }
}
```

2. Run the following command to rebuild your Extensions

```bash
$ bin/orchestra build \
    --skip-import-sections \
    --database-skip-import-data \
    --database-skip-import-structure \
    --skip-create-author \
    --skip-seeders \
    --skip-git-reset \
    --skip-composer \
    --skip-postbuild
```

## Basic Usage

This extension adds a new field called "Reflection". It can be added to sections like any other field. When saving an entry, Reflection field creates an internal `data` structure similar to what Symphony provides on the front-end. Besides the field data, contextual parameters like the root and workspace paths and the section handle are available as well.

```xml
<data>
	<params>
		<today>2021-01-12</today>
		<current-time>20:34</current-time>
		<this-year>2021</this-year>
		<this-month>01</this-month>
		<this-day>12</this-day>
		<timezone>+02:00</timezone>
		<website-name>Example Website</website-name>
		<root>https://example.com</root>
		<workspace>https://example.com/workspace</workspace>
		<http-host>example.com</http-host>
		<upload-limit>5242880</upload-limit>
		<symphony-version>2.7.10</symphony-version>
	</params>
	<reflection-field-handle>
		<section id="…" handle="…">…</section>
		<entry id="…">
			<field-one>…</field-one>
			<field-two>
				<item handle="…">…</item>
				<item handle="…">…</item>
			</field-two>
			<system-date>
				<created iso="…" timestamp="…" time="…" weekday="…" offset="…">…</created>
				<modified iso="…" timestamp="…" time="…" weekday="…" offset="…">…</modified>
			</system-date>
		</entry>
	</reflection-field-handle>
</data>
```

_**Note:** Version 2.0 changed the `data` structure to conform with the front-end. The `root` and `workspace` nodes moved to the parameter pool. The `entry-id` node was removed as the id was already available on the `entry` node._

### XSLT Utilities

XSL templates are used to manipulate the XML data before building the reflection expression. Any template in `/workspace/utilities` can be attached to the field: it will be provided with the above XML. The extension expects you to return an XML structure again, that is then used inside the expression field.

### Expressions

Expressions are used to build the field's content. You can add static content or markup, dynamic values can be added using curly braces containing xPath expressions to find the needed data. If you don't use an XSLT utility, the xPath expression is evaluated against the above XML. If you transformed the source data using a template, the xPath is evaluated against the returned XML structure you created.

_**Note:** Version 2.0 also changed the way xPath expressions are processed and introduced [some bugs][1] and [irritation][2] regarding what kind of expressions were supposed to work – since release 2.0.4 **all valid xPath expressions** can be used. See this [comparison-chart][3] for detailed information and test results._

## Usage with XML Importer

Reflection Field assumes that the entry has already saved some data for the said field, and then seeks to update the database directly with the correct reflection generated content.
In the case of XML Importer this means that you have to include the Reflection field within the import items, otherwise XML Importer will not find any data within your database to update.
You can insert an empty string, this would be sufficient for Reflection Field to be able to save the necessary data.

## About

### Requirements

- This extension works with PHP 7.4 or above.

### Dependencies

This extension depends on the following Composer libraries:

-   [Symphony CMS: Extended Base Class Library][dep-symphony-extended]

## Support

If you believe you have found a bug, please report it using the [GitHub issue tracker][ext-issues], or better yet, fork the library and submit a pull request.

## Contributing

We encourage you to contribute to this project. Please check out the [Contributing to this project][doc-CONTRIBUTING] documentation for guidelines about how to get involved.

## Author
-   Symphony Community - <https://github.com/symphonists/reflectionfield/graphs/contributors>
-   Alannah Kearney - <https://github.com/pointybeard>
-   See also the list of [contributors][ext-contributor] who participated in this project

## License
"Reflection Field for Symphony CMS" is released under the MIT License. See [LICENCE][doc-LICENCE] for details.

[doc-CONTRIBUTING]: https://github.com/pointybeard/symext-reflectionfield/blob/master/CONTRIBUTING.md
[doc-LICENCE]: http://www.opensource.org/licenses/MIT
[dep-symphony-extended]: https://github.com/pointybeard/symphony-extended
[ext-issues]: https://github.com/pointybeard/symext-reflectionfield/issues
[ext-Symphony-cms]: http://getsymphony.com
[ext-Orchestra]: https://github.com/pointybeard/orchestra
[ext-contributor]: https://github.com/pointybeard/symext-reflectionfield/contributors
[ext-docs]: https://github.com/pointybeard/symext-reflectionfield/blob/master/.docs/toc.md

[1]: https://github.com/symphonists/reflectionfield/issues/34
[2]: https://github.com/symphonists/reflectionfield/issues/35
[3]: https://github.com/symphonists/reflectionfield/issues/35#issuecomment-363224927
