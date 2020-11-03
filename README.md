# Diatom

*This template framework is a quick starting point. The `src/kernel.php` file aims to be everything necessary to begin making a website. There are additional classes to ensure that most goals can be met when it comes to authoring a content-based site.*


> This file is written in an extended markdown that includes definition lists (`<dl>`), disclosure elements (`<details>`) as well as many other inline elements such as `<cite>`,  `<dfn>`, `<abbr>`, `<small>`, `<mark>` and `<q>`, `<input>` checklists and `<table>`. Raw HTML is never allowed, for reasons that become clearer with use.

## Overviews

This framework is focused on creating reusable templates ({Hypertext Markup Language % HTML}) and allowing them to be routed, reused and organized pretty well by default. If more control is desired, some light programming can facilitate that.

### Requirements

The bulk of authorship is __completely standard__ xHTML, {Cascading Stylesheets  % CSS}, JavaScript. Then to program things, use PHP and reference [php.net](http://php.net) for misc. documentation (there are no dependencies).

- Linux or Mac OS)
- php 7.4+ - `brew install php`  to get that if needed
- Solid understanding of the Document Object Model
- familiarity with xpath ([a good resource](https://devhints.io/xpath))

### Quickstart

1. [clone repo](https://github.com/brendanmetzger/diatom) (or within github, fork or "use template" )
2. run `bin/server` from the application root directory
3. Have at it

## Use Cases

### Basic

Create new html files. Set processing instructions to deal with them. Append JS files wherever you want as `<script src='file.js'>`, don't worry about lazy loading, domready or anything, it will just work. Same with CSS, just embed a style element or link wherever, and it will find its way to the right spot in the template.

*note* There is a JavaScript autoload framework that provides functionality for loading scripts (see `views/js/autoload.js`)--but it is specifically designed to never be interacted with, or have to remember any of it's methodology to use--simply write JS, and embed in a `<script>` by reference or embedded directly.

### Some Programming

Routing is done with callbacks. In `index.php` file, set routes to enhance basic templates, receive webhooks, generate custom templates, accept parameters.For more complicated methods, a controller can be specified as a callback. Some guidance on when/how to use controllers:

- A good design technique to employ; methods in a controller should be prepared share a base template (forms, listings, cards) and customize that as necessary.
- The anonymous class should inherit from the abstract `controller` class somewhere in the foodchain

### Lots more programming

Create models, work with a database, connect to APIs for Oauth, etc.

Almost any site could employ some feature of this framework methodology. At its very lightest, an application can be an easily editable boilerplate producer--incredibly convenient for sites that don't require user-generated content. The next step up would be a framework that does employ some sort of database, but still has pretty standard layouts and several pages of boilerplate--this framework makes an excellent template preprocessor.

An API could be facilitated with the routing paradigm, or a receiver of webhooks.

## Templating

Templating is a powerful feature, and it stems from a premise that all content authored is a [Document](https://en.wikipedia.org/wiki/Document_Object_Model), and thus, can be sliced apart an queried. Any template can insert other documents, or parts of other documents as desired. Any markdown document is automatically parsed into a valid Document, and thus, adheres to the same principles.

- escaping: done by default
- conditions unnecessary: set datapoint to null (or undefined) to remove node
- yield, insert, and iterate components simply
- tested: templates can only be html, so, pretty safe when it comes to injection--they will never be exposed to a programming language.
- author in markdown (slightly modified) or strict xHTML
- use [template literals](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Template_literals) to embed a variables (`${expression}`)
- embed scripts directly into the template; to lazy load, add `<?render behavior?>` to your document (see list below) [autoload-js]
- CSS, similar to javascript, can be written in a `<style>` element directly in any page or a stylesheet added with `<link/>`.

## Stubs

: insert
  An ~insert~ is a direct call to another piece of content that lives at the end of a path
  `<!-- insert path/to/file.html-->` inserts a file directly
  `<!-- insert endpoint -->` inserts the result of a routed or dynamic url result

: Yield
  A ~yield~ stub means some programatic  aspect has determined how and what `Document` or `Element` will be inserted.
  `<!-- yield -->` on its own will default to inserting the result of the `Route::delegate` operation, and it is scoped to the  response object. *It replaces it's next `Element` sibling.*
  `<!-- yield keyword -->` also replaces with keyword from dynamic route; `$this->yield('keyword', string path or <Document>)`
  `<!-- yield keyword -->` similar to above but performs an *insert* instead of  swapping next sibling
  ~yield~ stubs do nothing if the content they stub out is unspecified or does not exist
: iterate
  The ~iterate~ stub grabs the `nextSibling` and continuously inserts that Element over the iteration of some dataset
  This is (probably) most appropriate to use in conjunction with template variables.

### Caveats

#### variables cannot mix contexts:

`<li>${A} ${B}</li>` is Ok, A and B share the same context

`<li>${A} <span>${B}</span></li>` is **not** ok:  B is in an `Element` adjacent to a `Text` node (which contains A). The variable parser will ignore these conditions.  (TODO;  reconsider this, if it can be done elegantly)

`<li><span>${A}</span> <span>${B}</span></li>` is OK as both variables have a single nodetype (`Text`) as a context

### Renderers

: canonical
  reorders the document to place (and organize) `<style>`, `<link>`, `<meta>`  into the `<head>` of the Document
: sections
  recursively looks for heading elements and builds sections around them.
: behavior
  behavior finds all `<script>` elements and converts/encodes:
  `<script src="path"></script>`  to `<script>Kit.script(path);</script>`
  `<script>code</script>`  to `<script>Kit.script(data://uri,b64 code);</script>`

## Application

### Directory Layout

: view/
  contains template files, CSS, JavaScript, media--essentially the document root
  : pages/
      html files are stored here
      the assumed layout,  derived from `index.html` file is stored here
  : src/
      holds all php files
  : data/
      xml, json, configs--anything that doesn't want to be tracked in a main repo but is integral to site functionality

### Output Formats

The response type will honor the extension of the 'file' requested, and default to html if no extension is  provided. The differentitation between dynamic routes and actual file endpoints is invisible. Thus, `random.jpg` could be a file, a template, a route callback, or a controller and the server response would be pretty much the same.

- content-type is assumed return text/html (.html) if unspecified
- the extension determines the content-type (simply)

### Routing

TODO

### Models

TODO