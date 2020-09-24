# Diatom

This template framework is a quick starting point. The `kernal.php` file aims to be everything necessary to begin making a website. There are additional classes to ensure that most goals can be met when it comes to authoring a content-based site.

## Requirements

- Linux or Mac OS (and a package installer  only if needed)
- php 7.4+ (soon php 8)



## Use cases

### Simple

If a website is, say, 10 pages of basic content, you are basically done, just hone your homepage in `pages/index.html`, add a `yield` spot for swapping in new pages, such as `pages/about.html`. Proceed to make the pages, and remember that you don't need to add all the boilerplate, because that will exist in `pages/index.html`

#### Things you can do out of the box
- use template variables
- embed partial templates
- insert custom javascript and css wherever you want


### More Involved

If you need to add authorship capabilities for others without access, there are facilities for that.

## Templating

### Embedding stylesheets and links


``` style

/* The custom markdown parser will look for a flag after the code fence embed style/script,
   depending on the flag. This will be unseen in the Diatom framework markdown, but visible
   in traditional markdown. Delete this after you get a gist (if you like) */

body {
  padding: 10rem;
  background-color: #f8f8f8;
}

h1 {font-size: 400%;}

section {
  margin: 2rem -2rem;
  padding: 2rem;
  background-color: #fff;
}

```

