let stylesheet = document.head.appendChild(document.createElement('style'));
stylesheet.textContent = `
*[data-doc] {outline-offset: -0.5em;}
body.editing main:focus-within { background-color:#EEE }
body.inserting main:focus-within { background-color:yellow }
*[contenteditable]:hover { outline: 1px dashed blue; }
*[contenteditable]:focus { outline: none; background-color:#fff; color:#000; box-shadow: 0 0 0 1rem rgba(255,255,255,1);}
`.trim();

addEventListener('dblclick', function(evt) {
  
  if (document.body.classList.contains('editing') || ! evt.metaKey) return;

  let target = evt.target;
  let line   = target.dataset.line || 0;
  let col    = (target.nodeName.length + 3) + (window.getSelection().focusOffset || 0);
  
  
  while (! target.dataset.doc) {
    target = target.parentNode;
    col+=2;
  }
  window.open(`txmt://open?url=file://${atob(target.dataset.doc)}&line=${line}&column=${col}`); // atom:// also avail

}, false);


var buffer = '';
var anchor = 0;

var highlight = stylesheet.sheet.cssRules[0];

document.addEventListener('keydown', evt => {
  let status = document.body.classList;
  
  if (evt.metaKey) {
    document.addEventListener('keyup', removeHighlight);
    highlight.style.outline = '0.5em solid rgba(255, 255, 0,0.5)';
    // setTimeout(removeHighlight, 10000);
    
  }
  
  if (status.contains('inserting')) {
    if (evt.shiftKey && evt.key === '>') {
      evt.preventDefault();
      let selection = window.getSelection();
      let node = selection.anchorNode;
      let tag = node.splitText(anchor-buffer.length).splitText(selection.anchorOffset).previousSibling;
      let swap = document.createElement(tag.textContent);
      swap.innerHTML = buffer;
      
      tag.parentNode.replaceChild(swap, tag);
      buffer = '';
      document.body.classList.remove('inserting');
      
    }
  } else if (evt.shiftKey && evt.key === '<') {
    let selection = window.getSelection();
    document.body.classList.add('inserting');
    buffer = selection.toString();
    anchor = selection.anchorOffset;
    evt.preventDefault();
  }
  
  if (evt.key == 'Enter') {
    evt.preventDefault();
    let clone = evt.target.parentNode.insertBefore(evt.target.cloneNode(), evt.target.nextSibling);
    clone.innerHTML = '';
    clone.dataset.crud = 'create';
    
    clone.addEventListener('blur', processElementChange, false);
    clone.addEventListener('focus', setHistory, false);
    
    clone.focus();
    
    // let range = document.createRange();
    // range.selectNodeContents(clone);
    // let selection = window.getSelection();
    //     selection.removeAllRanges();
    //     selection.addRange(range);
  }
  
  if (evt.key === 'Escape') {
    
    // todo undo buffer action if inserting
    
    let [cls, act, evt] = status.contains('editing') 
                        ? ['remove', 'removeAttribute', 'removeEventListener']
                        : [   'add',    'setAttribute',    'addEventListener'];
    
    status[cls]('editing');
    
    document.querySelectorAll('*[data-id]').forEach(  node => {
      node[act]('contenteditable', true);
      node[act]('spellcheck', true);
      node[evt]('blur', processElementChange, false);
      node[evt]('selectstart', showToolbar, false);
      node[evt]('focus', setHistory, false);
    });
  }
});

function showToolbar(evt) {
  // console.log(evt);
}

function setHistory(evt) {
  
  if (Notification.permission !== 'granted') {
    Notification.requestPermission();
  }
  
  this.history = this.innerHTML;
}



function processElementChange(evt) {
  if (this.history === this.innerHTML) return;
  
  let path  = this.parentNode;
  let data = this.innerHTML.replace(/\sxmlns="[^"]+"|&nbsp;/g, '');

  while (! path.dataset.doc) path = path.parentNode;
  
  let r = new Request({
    load: e => {
      let notify = new Notification(e.currentTarget.responseText);
      setTimeout(notify.close.bind(notify), 2000);
    }
  });
  /*
    TODO set a delete method which evaluates an empty string
  */
  let crud = (this.dataset.crud || 'update').toLowerCase();
  r.put(`/update/${path.dataset.doc}-${this.dataset.id}/${crud}`, data);
};

function removeHighlight(evt) {
  
  highlight.style.outline = 'none';  
  document.removeEventListener('keyup', removeHighlight);
  
}