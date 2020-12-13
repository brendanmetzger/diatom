let stylesheet = document.head.appendChild(document.createElement('style'));

stylesheet.textContent = `
.templates *[data-doc]:hover {outline-offset: calc(var(--standard) * -1);outline:var(--standard) solid rgb(255 255 0 / 0.75);position:relative;}
*[data-doc]:hover::before {white-space:nowrap;position: absolute; content:attr(data-doc);top:0;left:0;writing-mode: vertical-rl;padding: 0.25rem;font-family:'Courier New' !important;font-weight:400;font-size:10px;background-color:#fff;}
body.editing main:focus-within { background-color:#EEE }
 {highlight.style.outline = 'var(--standard) solid rgb(255 255 0 / 0.75)';}
*[contenteditable]:hover { outline: 1px dashed blue; }
*[contenteditable]:focus { outline: none; background-color:#fff; color:#000; box-shadow: 0 0 0 1rem rgba(255,255,255,1);}
`.trim();

addEventListener('dblclick', function(evt) {

  if (document.body.classList.contains('editing') || ! evt.metaKey) return;

  let target = evt.target;
  let root   = document.body.dataset.root;
  while (! target.dataset.doc) {
    target = target.parentNode;
  }
  window.open(`${document.body.dataset.prompt}?url=file://${root}/${target.dataset.doc}`);

}, false);


document.addEventListener('keydown', evt => {
  let status = document.body.classList;
  if (evt.metaKey && evt.shiftKey) {
    document.addEventListener('keyup', removeHighlight);
    status.add('templates');
  }


  if (evt.key === 'Escape') {

    // todo undo buffer action if inserting

    let [cls, act, evt] = status.contains('editing')
                        ? ['remove', 'removeAttribute', 'removeEventListener']
                        : [   'add',    'setAttribute',    'addEventListener'];

    status[cls]('editing');

    document.querySelectorAll('*[data-path]').forEach( node => {
      node[act]('contenteditable', true);
      node[act]('spellcheck', true);
      node[evt]('blur', processElementChange, false);
      node[evt]('selectstart', showToolbar, false);
    });
  }
});

function showToolbar(evt) {
  // console.log(evt);
}



function processElementChange(evt) {

  let context  = this;
  while (! context.dataset.doc) context = context.parentNode;

  Request.PUT('edit/update.xml', context).then(result => {
    this.innerHTML = result.querySelector(`*[data-path='${this.dataset.path}']`).innerHTML;
  });

};

function removeHighlight(evt) {
  document.body.classList.remove('templates');
  document.removeEventListener('keyup', removeHighlight);
}
