# Diatom

*Philosophically this framework is a pattern-making aid for creating and organizing well-structured documents. There are few configuration rules, and no rigid structure to start things off.*


> This file is written in an extended markdown that includes definition lists (`<dl>`), disclosure elements (`<details>`) as well as many other inline elements such as `<time>`, `<data>`, `<cite>`,  `<dfn>`, `<abbr>`, `<small>`, `<mark>` and `<q>`, `<input>` checklists and `<table>`. Raw HTML is never allowed, for reasons that become clearer with use.

## Definitions

: Document
  A Document refers to either a *structurally valid* {eXtensible markup language % XML} or {hypertext markup language % HTML} text file rendering a Document Object Model
  Assume any grammer or variables mentioning 'document' or 'dom' are structured markup  *Documents* unless otherwise specified, ie., "a {javascript object notation % json} document"
  Documents are modeled information, and care should be taken when interacting as one would on a database. Ie., `$node->nodeValue = "<em>neat</em>"` is incorrect in contrast to `$node->appendChild(new Element('em', 'neat'));` This is not a visual task, but creating a context that could be visualized, queried, reformatted, etc.
: Template
  A Document that has been loaded to be parsed and Rendered
  Parsing a Document involves embedding other Documents and variables via ~yield~, ~insert~ and ~iterate~ and looking for template variables to be swapped out with mapped items from a data set
: Render
  A function or method that modifies a Document's structure
  Templates carryout the rendering process
  Renders can be specified to be performed in numerous ways: within a Document via processing instructions,specified in a dynamic route's configuration argument, or defined statically as before/after methods during Template parsing, ie., `Render::set('before', fn($dom) => // modify $dom);`
: Route
  An endpoint that can be specified to return data corresponding to a user's request
  Data can be any format, but usually (and defaults to) a Document (that can be ~Rendered~)
  Routes can defualt to actual html files in a directory, or can be specifed in `index.html`

## Overviews

This framework is focused on creating reusable templates ({Hypertext Markup Language % HTML}) and allowing them to be routed, reused and organized pretty well by default. If more control is desired, some light programming can facilitate that.

### Requirements

The bulk of authorship is __completely standard__ xHTML, {Cascading Stylesheets % CSS}, JavaScript. Then to program things, use PHP and reference [php.net](http://php.net) for misc. documentation (there are no dependencies).

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

// insert ../docs/*.md