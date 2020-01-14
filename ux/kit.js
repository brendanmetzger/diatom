/*
  Use case is primarily mousemove or touchmove. This method reterns the angle (in rad)
  created between the centerpoint of the eventTarget and cursor/finger [x,y] at time of event. 
*/
Object.defineProperty(Event.prototype, 'theta', {
  get: function() {
    // can prob cache this value as long as nothing changes sizes
    var rect  = this.target.getBoundingClientRect();

    [x,y] = getOffset.call(this, rect);

    var abscissa = x - (rect.width / 2)
    var ordinate =  (rect.height / 2) - y;
    return Math.PI + Math.atan2(abscissa, ordinate);
  }
});

/* I don't remember the use case of this */

Object.defineProperty(Event.prototype, 'rando', {
  get: (function(threshold) {
    console.log(this);
    if (threshold < 0.5) {
      return function() {
        return this;
      };
    } else {
      return function() {
        return this.type;
      }
    }
  })(Math.random())
});

var getOffset = (function(touchscreen) {
  if (touchscreen) {
    return function(rect) {
      return [(this.touches[0].clientX - rect.left), (this.touches[0].clientY - rect.top)];
    }
  }
  return function() {
    return [(this.offsetX || this.layerX), (this.offsetY || this.layerY)];
  }  
})(app.mobile);

addEventListener('mousemove', function(evt) {
  console.log(evt.theta);
});

var SVG = function (node, width, height) {
  this.NS = Object.freeze({
    svg:   'http://www.w3.org/2000/svg',
    xlink: 'http://www.w3.org/1999/xlink'
  });
  this.width = width;
  this.height = height;
  this.element = this.createElement('svg', {
    'xmlns:xlink': this.NS.xlink, 'xmlns': this.NS.svg, 'version': 1.1, 'viewBox': `0 0 ${width} ${height}`
  }, node);
  
  this.point = this.element.createSVGPoint();
};


SVG.prototype.createElement = function (name, opt, parent) {
  var node = document.createElementNS(this.NS.svg, name);
  for (var key in opt) {
    if (key == "xlink:href") {
      node.setAttributeNS(this.NS.xlink, 'href', opt[key]);
    } else {
      node.setAttribute(key, opt[key]);
    }
  }
  return parent === null ? node : (parent || this.element).appendChild(node);
};

// Get point in global SVG space
SVG.prototype.cursorPoint = function (evt) {
  this.point.x = evt.clientX; 
  this.point.y = evt.clientY;
  return this.point.matrixTransform(this.element.getScreenCTM().inverse());
};

SVG.prototype.b64url = function (styles) {
  var clone = this.element.cloneNode(true);
  this.createElement('style', null, clone).textContent = styles;
  return `url(data:image/svg+xml;base64,${btoa(clone.outerHTML)})`;
};


// consider a 'Request' object, and a JSONP example using 'qwiki' (quick wiki) or weather