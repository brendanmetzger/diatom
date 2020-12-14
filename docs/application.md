# Application

## Directory Layout

: view/
  contains template files, CSS, JavaScript, media--essentially the document root
  : pages/

html files are stored here

the assumed layout,  derived from `index.html` file is stored here

: src/

holds all php files

serves as the application root, which means when a file is explicitely declareded as relative (ie, `./file` or `../file`) then the reference point is this directory. This may not seem intuitive, but it is a hard rule, and easy to remember, which is especially useful when the context of a file is murky, such as deeply namespace files n such

: data/

xml, json, configs--anything that doesn't want to be tracked in a main repo but is integral to site functionality

### Output Formats

The response type will honor the extension of the 'file' requested, and default to html if no extension is  provided. The differentitation between dynamic routes and actual file endpoints is invisible. Thus, `random.jpg` could be a file, a template, a route callback, or a controller and the server response would be pretty much the same.

- content-type is assumed return text/html (.html) if unspecified
- the extension determines the content-type (simply)

### Routing

Routes are declared in `view/pages/` or `view/index.php`; **always** look there at the first argument in the url to find where the actual page might be called.
