
// the % mod operator does not comput negative numbers correctly; use int.mod(int) if this matters
Number.prototype.mod = function (n) {
  return ( (this % n) + n ) % n;
};

// given n, returns [rows,cols] (approx) so aspect is filled with n SQUARE items
var balance = function (n, aspect) {
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

// To address an issue in the modulus operator that doesn't deal with negative numbers as expected
// Number.prototype.mod = n => ((this % n) + n) % n;

var getOffset = (function(touchscreen) {
  return touchscreen ?
  rect => [(this.touches[0].clientX - rect.left), (this.touches[0].clientY - rect.top)]
  : () => [(this.offsetX || this.layerX), (this.offsetY || this.layerY)];
})(KIT.mobile);


var SVG = function (width, height, context) {
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
    
  },
  b64url: function (styles) {
    var clone = this.root.cloneNode(true);
    this.createElement('style', null, clone).textContent = styles;
    return `url(data:image/svg+xml;base64,${btoa(clone.outerHTML)})`;
  }
});

var Request = function (callbacks, timeout = 5000) {
  this.xhr = new XMLHttpRequest();
  this.xhr.overrideMimeType('text/xml');
  this.xhr.timeout = timeout;
  for (let action in callbacks) {
    this.xhr.addEventListener(action, callbacks[action].bind(this), false);
  }
  return this;
};

Request.prototype = {
  get: function (url) {
    this.make('GET', url);
  },
  put: function (url, data) {
    this.make('PUT', url, data);
  },
  make: function (type, url, data = null) {
    this.url = url;
    this.xhr.open(type, url);
    this.xhr.setRequestHeader('yield', 'xhr');
    this.xhr.send(data);
  }
};


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
