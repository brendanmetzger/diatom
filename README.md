# Diatom


*This template framework is a quick starting point. The `src/kernel.php` file aims to be everything necessary to begin making a website. There are additional classes to ensure that most goals can be met when it comes to authoring a content-based site.*


## Guidelines

A pure html site is rather {custom:   burdensome} to manage if you treat html as extraneous slop; this templating system is strict, and doesn't allow all-too-common errors (CDATA mishaps, unencoded entities, unclosed elements, etc.) Rather, a an error is thrown and offer guidance on where to correct the file conveniently and immediately.

----

- Linux or Mac OS (and a package installer  only if needed)
- php 7.4+ (soon php 8)
- valid xHTML (that's XML, close your elements!)
- CSS
- Javascript


### Languages

JavaScript can be a mess, and it can be frighteningly complicated; this framework alleviates some of that by allowing you great freedom to just write your javascript wherever you want, and include it as a script with a src, or just plop it right into a template. Don't worry about `DOMready` or any of that stuff, just write principled javascript at the point of interest and it should work out alright.


## Use cases

1. One
2. Two
3. Three
4. Four

### Simple

If a website is, say, 10 pages of basic content, you are basically done, just hone your homepage in `pages/index.html`, add a ` yield` spot for swapping in new pages, such as `views/vpages/about.html`. Proceed to make the pages, and remember that you don't need to add all the boilerplate, because that will exist in `views/pages/index.html`

#### Things you can do out of the box
- use template variables
- embed partial templates
- insert custom javascript and css wherever you want


### More Involved

If you need to add authorship capabilities for others without access, there are facilities for that.

## Templating

- use `${key}` to embed a variable
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

section ul {margin: 1rem; padding: 0; list-style-type: none}

section {
  margin: 2rem -2rem;
  padding: 2rem;
  background-color: #FAFAFA;
}

a,code {color: rgb(255 0 128);}

code {
  background-color: #fff;
  box-shadow: 0 0 0 0.5em #fff;
  font-style: normal;
}


```

