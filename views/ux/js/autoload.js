addEventListener('DOMContentLoaded', (function(s, js, cache) {
  self.KIT = Object.freeze({
    script : (...a) => js.push(['src','async'].reduce((s,v,i) => {s[v]=a[i]; return s}, s.cloneNode())),
    module : (k) => (k in cache) ? cache[k] : (cache[k.name] = new k),
    remove : (k) => delete cache[k],
  });
  return (evt) => {
    document.documentElement.classList.add(['click','touch'][+(/mobile|iPhone/i).test(navigator.userAgent)]);
    document.documentElement.classList.add('domready');
    js.forEach(Node.prototype.appendChild.bind(document.head));
  };
})(document.createElement('script'), [], Object.create(null)));