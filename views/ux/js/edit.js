let stylesheet = document.head.appendChild(document.createElement('style'));

stylesheet.textContent = `
.templates *[data-doc]:hover,
.templates[data-doc]:hover {outline-offset: -0.5em; outline:0.5em solid #000;}
*[data-doc]:hover::before {box-sizing:border-box;white-space:nowrap;position: fixed; content:attr(data-doc);bottom:0;right:0;padding:5px 10px;font-weight:400;font-size:10px;background-color:rgb(255 255 255 / 0.8);min-width:100%;line-height:1;text-align:right}
body.editing main:focus-within { background-color:#EEE }


*[contenteditable]:hover { outline: 1px dashed blue; }
*[contenteditable]:focus { outline: none; background-color:#fff; color:#000; box-shadow: 0 0 0 1rem rgba(255,255,255,1);}
`.trim();

addEventListener('dblclick', function(evt) {

  if (document.documentElement.classList.contains('editing') || ! evt.metaKey) return;

  let target = evt.target;
  let root   = document.body.dataset.root;
  while (! target.dataset.doc) {
    target = target.parentNode;
  }
  window.open(`${document.body.dataset.prompt}?url=file://${root}/${target.dataset.doc}`);

}, false);


document.addEventListener('keydown', evt => {
  let status = document.documentElement.classList;
  if (evt.metaKey && evt.shiftKey) {
    console.log('hererere');
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

  Request.PUT('system/update.xml', context).then(result => {
    this.innerHTML = result.querySelector(`*[data-path='${this.dataset.path}']`).innerHTML;
  });

};

function removeHighlight(evt) {
  document.documentElement.classList.remove('templates');
  document.removeEventListener('keyup', removeHighlight);
}
