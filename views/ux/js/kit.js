const ext    = (m => document.createElement('canvas').toDataURL(m).indexOf(`data:${m}`) == 0)('image/webp') ? 'webp' : 'jpg';

// the % mod operator does not comput negative numbers correctly; use int.mod(int) if this matters
Number.prototype.mod = function (n) {
  return ( (this % n) + n ) % n;
};

// given n, returns [rows,cols] (approx) so aspect is filled with n SQUARE items
const balance = function (n, aspect) {
 var max = Math.sqrt(n) * Math.sqrt(aspect);
 return [max, n / max].map(Math.round);
};

/*
  Use case is primarily mousemove or touchmove. This method reterns the angle (in rad)
  created between the centerpoint of the eventTarget and cursor/finger [x,y] at time of event. 
*/

Object.defineProperty(Event.prototype, 'theta', {
  get: function() {
    // can prob cache this value as long as nothing changes sizes
    var rect  = this.target.getBoundingClientRect();

    [x,y] = getOffset.call(this, rect);

    var abscissa = x - (rect.width / 2);
    var ordinate =  (rect.height / 2) - y;
    return Math.PI + Math.atan2(abscissa, ordinate);
  }
});



const getOffset = (function(touchscreen) {
  return touchscreen ?
  rect => [(this.touches[0].clientX - rect.left), (this.touches[0].clientY - rect.top)]
  : () => [(this.offsetX || this.layerX), (this.offsetY || this.layerY)];
})(KIT.mobile);


const SVG = function (width, height, context) {
  this.NS = Object.freeze({
    svg:   'http://www.w3.org/2000/svg',
    xlink: 'http://www.w3.org/1999/xlink'
  });
  this.width  = width;
  this.height = height;
  this.root   = this.add('svg', {
    'xmlns:xlink': this.NS.xlink, 'xmlns': this.NS.svg, 'version': 1.1, 'viewBox': `0 0 ${width} ${height}`
  }, null);
  
  if (context) context.appendChild(this.root);
  
  this.point = this.root.createSVGPoint();
};

Object.assign(SVG.prototype, {
  add: function (name, opt, parent) {
    var node = document.createElementNS(this.NS.svg, name);
    for (var key in opt) {
      if (key == "xlink:href") {
        node.setAttributeNS(this.NS.xlink, 'href', opt[key]);
      } else {
        node.setAttribute(key, opt[key]);
      }
    }
    return parent === null ? node : (parent || this.root).appendChild(node);
  },
  cursorPoint: function (evt) { // Get point in global SVG space
    this.point.x = evt.clientX; 
    this.point.y = evt.clientY;
    return this.point.matrixTransform(this.root.getScreenCTM().inverse());
    
  }
});

class Request {
  constructor(callbacks, timeout = 5000) {
    this.xhr = new XMLHttpRequest();
    this.xhr.timeout = timeout;
    this.xhr.overrideMimeType('text/xml');
    for (let action in callbacks) {
      this.xhr.addEventListener(action, callbacks[action].bind(this), false);
    }
    this.xhr.addEventListener('progress', evt => {
      console.log(evt.loaded);
      if (evt.lengthComputable) {
        let complete = (evt.loaded / evt.total) * 100;
        console.log(evt, `${complete}% complete`);
        // this.style.backgroundImage = `conic-gradient(white 0% ${complete}%, yellow ${complete}% 100%)`;
      }
    });
    return this;
  }
  
  static GET (url, headers = {}) {
    return Request.make('GET', url, null, headers);
  }
  
  static PUT (url, data, headers = {}) {
    // headers["Content-Type"] = "application/x-www-form-urlencoded"
    return Request.make('PUT', url, data, headers);
  }
  
  static POST (url, data, headers = {}) {
    return Request.make('POST', url, data, headers);
  }
  
  static make (type, url, data, headers) {
    url = new URL(url, location.origin);
    headers.yield = 'XHR';
    
    return new Promise((resolve, reject) => {
      let dot = url.pathname.lastIndexOf('.');
      let ext = dot > 0 ? url.pathname.slice(dot+1) : 'json';
      let instance = new Request({
        load: evt => {
          if (evt.target.status >= 400) reject.call(evt, evt.target);
          else resolve.call(evt, evt.target.response, evt);
        },
        error: reject
      });
      
      
      
      instance.xhr.responseType = ({webp:'blob',jpg:'blob',png:'blob',md:'text',txt:'text',json:'json'})[ext] || 'document';
      
      
      instance.xhr.open(type, url.href);
      for(let key in headers) instance.xhr.setRequestHeader(key, headers[key]);
      instance.xhr.send(data);
            
    });
    
  }
}

// for quick lookups of searchable string (see soundex on wikipedia...)
Object.defineProperty(String.prototype, 'soundex', {
  get: function() {
    let a  = this.toLowerCase().replace(/[^a-z]/, '').split(''),
        id = a.shift().toUpperCase(),
        c  = ['aeiouyhw', 'bfpv','cgjkqsxz','dt','l','mn','r'].map(g => g.split('')),
        re = /([1-9])(?=\1+)|0/g;
    return id + (a.map(letter => c.findIndex(g => g.includes(letter))).join('').replace(re, '') + '000').slice(0,3);
  }
});


