let stylesheet = document.head.appendChild(document.createElement('style'));

stylesheet.textContent = `
*[data-doc] {outline-offset: 0}
body.editing main:focus-within { background-color:#EEE }
body.inserting main:focus-within { background-color:yellow }
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
  window.open(`txmt://open?url=file://${root}/${target.dataset.doc}`); // atom:// also avail

}, false);


var highlight = stylesheet.sheet.cssRules[0];

document.addEventListener('keydown', evt => {
  let status = document.body.classList;
  
  if (evt.metaKey) {
    document.addEventListener('keyup', removeHighlight);
    highlight.style.outline = '0.125em solid rgb(255 0 0 / 0.5)';
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
      // node[evt]('focus', setHistory, false);
    });
  }
});

function showToolbar(evt) {
  // console.log(evt);
}



function processElementChange(evt) {
  // if (this.history === this.innerHTML) return;
  
  let context  = this.parentNode;
  while (! context.dataset.doc) context = context.parentNode;

  Request.PUT('edit/update.xml', context).then(result => {
    this.innerHTML = result.querySelector(`*[data-path='${this.dataset.path}']`).innerHTML;
  });
};

function removeHighlight(evt) {
  highlight.style.outline = 'none';  
  document.removeEventListener('keyup', removeHighlight);
}