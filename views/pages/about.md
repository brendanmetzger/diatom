?title Philosophy, Usage, Etc.
?publish 1
?render sections
?author Brendan Metzger


``` style

article {
  background-color:#fafafa;
  padding: 5%;
  max-width: 75em;
  margin:1rem;
}

article > section:first-of-type {
  columns: var(--columns);
}

article > section:first-of-type p:first-of-type {
  font-size: 162.5%;
  line-height: 1;
}

section:first-of-type > h2 {
  margin: 0;
  padding: 1rem;
  border-bottom: var(--thin-rule);
}

h1 {
  background-image:url(/ux/media/diatomea.jpg);
  mix-blend-mode:multiply;
  font-family: dapifer-stencil, sans-serif;
  font-weight: 900;
  font-style: normal;
  background-size: auto 100%;
  padding-left: 1.5em;
  background-repeat: no-repeat;
  font-size: 6em;
  color: #444;
}
#usage {
  overflow: auto;
}
#usage > section {
  padding: 1rem;
  width: 33.3%;
  float: left;
}

```

# Diatom
#### An homage to websites

```

example

   formatted
   
   weirdly
   
   > this is that
   
```


## Philosophy


An era of frameworks began--at least for me--in the late aughts, which means that 90% of my work between now is tucked away in a repository and probably never to be resurrected.

Prior to that era, I have quite a bit of work that still holds up (at least technically), as it is just vanilla HTML/CSS. Something that I remember back then was how shitty browsers were, how hard it was to write JavaScript and CSS, how hard front-end debugging was. An now its sooooo much easier, yet, we have require.js and react, and I don't think I'm a luddite, but I don't quite get it.

As an homage to that era, I have been developing a system that allows an author to focus on constructing the user experience--html, ^^Cascading StyleSheets^^, and JavaScript--in a way that doesn't feel as inelegant or repetitive as a classic HTML site, but still allows an author to write almost exclusively in those languages (including markdown) if desired. There is still dynamic, data driven programming, but the templates take center stage and complex elements can be handled by adherence to [standard paradigms](#fn-paradigms) or easy api integrations.

Stylesheets and JavaScript can be embedded and encoded in numerous ways, extemporaneously or calculated, to match the ebb and flow of developing ideas. Templates themselves can be mixed and match and embedded, so duplication is avoided. Data can be modeled and applied, and controllers can be declared whenever an idea becomes too complicated for the defaults in place.

The last hurdle for a modern application is editing, and this is where creative breakthroughs occur.

The ultimate goal is to **build more interesting websites,** that could function as applications, but are developed more like the websites of yesteryear. They can be trivial, exploratory, narrative-based, utilize data, and any number of other goals that need not require administrative interfaces.
     
- Configuration is absolutely minimal, and it occurs in obvious places. (ie., templates are configured within the template itself)
- sites created in this platform can be optimized and cached for production environments as an archive.
- Optimized for ux and interactive content authorship
- Fairly robust sites can measure less than 1000 ^^Source Lines Of Code^^ (of php that is)
- content is authored by creating and organizing new html files (templates are html)
- custom programming and data processing can be configured in `Route` callbacks
- *Diatom* as a guiding concept melts away as an application develops its own character, and can be rewritten and amended without complaint as it is an independent piece of software.
- No dependencies


## Usage

### Most Basic

Create new html files. Set processing instructions to deal with them. Append JS files wherever you want as script src='file.js', don't worry about lazy loading, domready or anything, it will just work. Same with CSS, just embed a style element or link wherever, and it will find its way to the right spot in the template. This is the coolest part of the intire thing.


### Some Programming

In index.php file, set routes to enhance basic templates, receive webhooks, generate custom templates, accept parameters.

### Lots more programming

Create models, work with a database, connect to APIs for Oauth, etc.

Almost any site could employ some feature of this framework methodology. At its very lightest, an application can be an easily editable boilerplate producer--incredibly convenient for sites that don't require user-generated content.. The next step up would be a framework that does employ some sort of database, but still has pretty standard layouts and several pages of bolerplate--this framework makes an excellent template preprocessor.

An API could be facilitated with the routing paradigm, or a receiver of webhooks.

## Guidelines
A pure html site is rather burdensome to manage if you treat html as extraneous slop; this templating system is strict, and doesn't allow all-too-common errors (CDATA mishaps, unencoded entities, unclosed elements, etc.) Rather, a an error will we thrown and offer guidance on where to correct the template. So, the following a the starting point in terms of guidelines.

### Languages

- PHP at 7.4 or above.
- valid xHTML (that's XML, close your elements!)
- CSS
- Javascript

JavaScript can be a mess, and it can be frighteningly complicated; this framework alleviates some of that by allowing you great freedom to just write your javascript wherever you want, and include it as a script with a src, or just plop it right into a template. Don't worry about `DOMready` or any of that stuff, [that has been taken care of](#fn-lazyjs)

CSS, similar to javascript, can be linked and [included in any template](#fn-lazycss)

### Directory Layout

- the assumed layout is derived from index.html
- html files are stored in a directory called pages

### Output Formats

- content-type is assumed return text/html (.html) if unspecified

All of the above can, of course, be changed--but you wouldn't change a configuration file, you'd re-author parts of the program, because back to the philosophy, is that stuff going to change arbitrarily?



## Milestone Goals



// insert pages/todo.md //ul

``` script

document.querySelector('article > section:first-of-type').addEventListener('click', evt => {
  alert('you clicked the first section!');
});

```
