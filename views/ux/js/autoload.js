addEventListener('DOMContentLoaded', ((d, js, $) => {
  self.KIT = Object.freeze({
    script : (src) => {
      js.set(o = d.currentScript, n = d.createElement('script')); 
      [{name:'src', value:src}, ...o.attributes].map(k => n.setAttribute(k.name, k.value));
    },
    module : (k) => (k in $) ? $[k] : ($[k.name] = new k),
    remove : (k) => delete $[k],
  });
  return (evt) => {
    d.documentElement.classList.add(['click','touch'][+(/mobile|iPhone/i).test(navigator.userAgent)]);
    js.forEach((n,o) => o.replaceWith(n));
  };
})(document, new Map, Object.create(null)));