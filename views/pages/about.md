?publish 1
?render sections


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
section:first-of-type > h2 {
  margin: 0;
  padding: 1rem;
  border-bottom: var(--thin-rule);
}

h1 {
  background-image:url(/ux/media/diatomea.jpg);
  mix-blend-mode:multiply;
  background-size: auto 100%;
  padding-left: 1.25em;
  background-repeat: no-repeat;
  font-size: 6em;
  letter-spacing:-0.05em;
}

```

# Diatom
#### An homage to websites


## Philosophy

I was going through archived work one day and found that anything written between 3 and 12 years ago was pretty much non-functional. Oddly, anything older was mostly intact directories of html, css, and JavaScript. Granted, it took a lot of keystrokes to make websites back then, but at least those keystrokes aren't rendered meaningless by outdated and unsupported frameworks, databases, and fads.

I spent years and years working on a few projects that I am still very proud of today under the full realization that I will probably never see them again--many of which have work and concepts that I would love to revisit, if only for nostalgia.

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






// insert pages/todo.html

``` script
document.querySelector('article > section:first-of-type').addEventListener('click', evt => {
  alert('you clicked the first section!');
});

```
