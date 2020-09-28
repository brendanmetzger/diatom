# Diatom


*This template framework is a quick starting point. The `src/kernel.php` file aims to be everything necessary to begin making a website. There are additional classes to ensure that most goals can be met when it comes to authoring a content-based site.*


## Overview

This framework is primarly focused around creating reusable templates (HTML) and allowing them to be programmed elegantly by  avoiding parse errors (CDATA mishaps, unencoded entities, unclosed elements, etc.) that occur with non-validatable templates or markdown libraries.

Guidance is offered when mistakes occur, ensuring that all output is entirely valid, queryable, and can undergo mutations, etc.

### Some Principles

1. Do not over-configure
2. write code yourself when possible (and throughly read source when not)
3. Use agnostic tools to do anything complicated; Regular Expressions, Xpath/DOM, SQL
4. Honor thy Markup


### Requirements

The bulk of authorship is __unadulterated__ xHTML, CSS, JavaScript. Then to program things, PHP. There are no dependencies. 

- Linux or Mac OS)
- php 7.4+ - `[brew](https://brew.sh/) install php`  to get that if needed


## Use Cases


### Simple

If a website is, say, 10 pages of basic content, you are basically done, just hone your homepage in `pages/index.html`, add a ` yield` spot for swapping in new pages, such as `views/vpages/about.html`. Proceed to make the pages, and remember that you don't need to add all the boilerplate, because that will exist in `views/pages/index.html`


### More Involved

If you need to add authorship capabilities for others without access, there are facilities for that.

## Templating

Templating is a powerful feature, and it stems from a premise that all content authored is a [Document](https://en.wikipedia.org/wiki/Document_Object_Model), and thus, can be sliced apart an queried. Any template can insert other documents, or parts of other documents as desired. Any markdown document is automatically parsed into a valid Document, and thus, adheres to the same principles.

- author in markdown (slightly modified) or strict xHTML
- use [template literals](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Template_literals) to embed a variables (`${${expression}}`)
- embed scripts directly into the template; to lazy load, add `<?render behavior?>` to your document
- CSS, similar to javascript, can be written in a `<style>` element directly in any page or a stylesheet added with `<link/>`. 

## Authoring


### Most Basic

Create new html files. Set processing instructions to deal with them. Append JS files wherever you want as script src='file.js', don't worry about lazy loading, domready or anything, it will just work. Same with CSS, just embed a style element or link wherever, and it will find its way to the right spot in the template. This is the coolest part of the intire thing.


### Some Programming

Routing is done with callbacks. In `index.php` file, set routes to enhance basic templates, receive webhooks, generate custom templates, accept parameters.For more complicated methods, a controller can be specified as a callback. Some guidance on when/how to use controllers:

- A good design technique to employ; methods in a controller should be prepared share a base template (forms, listings, cards) and customize that as necessary.
- The anonymous class should inherit from the abstract `controller` class somewhere in the foodchain



### Lots more programming

Create models, work with a database, connect to APIs for Oauth, etc.

Almost any site could employ some feature of this framework methodology. At its very lightest, an application can be an easily editable boilerplate producer--incredibly convenient for sites that don't require user-generated content.. The next step up would be a framework that does employ some sort of database, but still has pretty standard layouts and several pages of bolerplate--this framework makes an excellent template preprocessor.

An API could be facilitated with the routing paradigm, or a receiver of webhooks.



### Directory Layout

- the assumed layout is derived from index.html
- html files are stored in a directory called pages

### Output Formats

- content-type is assumed return text/html (.html) if unspecified

All of the above can, of course, be changed--but you wouldn't change a configuration file, you'd re-author parts of the program, because back to the philosophy, is that stuff going to change arbitrarily?




### [|Embedding|](/dumb/example) stylesheets and links


``` style


/* The custom markdown parser will look for a flag after the code fence embed style/script,
   depending on the flag. This will be unseen in the Diatom framework markdown, but visible
   in traditional markdown. Delete this after you get a gist (if you like) */

@import url('https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:ital,wght@0,400;0,700;1,400&display=swap');

main {max-width: 100ch;}

body {
  padding: 10rem;
  background-color: #EEE;
  font-family: 'IBM Plex Mono', monospace;
}

h1 {font-size: 400%;}

section[id$=tasks] ul {list-style:none;}

section {
  margin: 2rem -2rem;
  padding: 2rem;
  background-color: #FAFAFA;
}

a,code {color: rgb(255 0 128);}

code {
  background-color: #fff;
  box-shadow: 0 0 0 0.25em rgb(255 255 255 / 0.75);
  font-style: normal;
  padding: 0 0.25em;
}


```

