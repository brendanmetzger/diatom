# Templates

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
: yield
  A ~yield~ stub means some programatic  aspect has determined how and what `Document` or `Element` will be inserted.
  `<!-- yield -->` on its own will default to inserting the result of the `Route::delegate` operation, and it is scoped to the  response object. *It replaces it's next `Element` sibling.*
  `<!-- yield keyword ! -->` similar to above but performs an *insert* instead of  swapping next sibling
  `<!-- yield keyword -->` also replaces with keyword from dynamic route; Tell the response what to do with `$this->yield('keyword', string path or <Document>)`
  `<!-- yield keyword -->` similar to above but performs an *insert* instead of  swapping next sibling
  ~yield~ stubs do nothing if the content they stub out is unspecified or does not exist
: iterate
  ~iterate~ stub grabs the `nextSibling` node, reinserting it against an enumerable dataset
  Template Variables are scoped to the node getting iteraterated. If you need to access a variable outside of the scope, wrap the variable: `${${parent.scope}} vs. ${data.block.scope}`

### Renderers

: canonical
  reorders the document to place (and organize) `<style>`, `<link>`, `<meta>`  into the `<head>` of the Document
: sections
  recursively looks for heading elements and builds sections around them.
: behavior
  behavior finds all `<script>` elements and converts/encodes:
  `<script src="path"></script>`  to `<script>Kit.script(path);</script>`
  `<script>code</script>`  to `<script>Kit.script(data://uri,b64 code);</script>`
