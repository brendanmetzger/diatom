?title Getting Started
?publish 2
?render sections

# Quickstart \ Guides

Open **views/pages/checklist.md** to ammend and edit this checklist

## Startup Tasks

- [x] specify a new port in `bin/server` if desired
- [ ] run `bin/server` to start a localhost, opens site in chrome by default
- [ ] edit `views/pages/index.html` to make changes (this readme is imported by default)
- [ ] create a new page in pages, add processing instructions to make it visible in the nav

## Explore Tasks

- [ ] Change port in `bin/php`
- [ ] Create a new file in `view/pages/`
- [ ] make a syntax error in an ^^html^^ document
- [ ] Checkout out `index.php`, note example route and play around

## Development Tasks

This application is often used on the ^^AWS^^ Platform, particularly *lightsail* for the server host, and *S3* for object storage.

- [ ] Set up `data/config.ini` file with keys
- Configure ligtsail server (this is only an overview see bin/lightsail.sh for more detail)
  - [ ] PHP + Apache
  - [ ] HTTP/2
  - [ ] Certbot Certificaties
  - [ ] Users/Group for web authorship
- [ ] init a git repo and set up remote
- [ ] review deployment strategy

// insert ../README.md //style

```script

document.querySelector('article[data-doc]').addEventListener('click', function(evt) {
  if (evt.target.nodeName == 'INPUT') {
    let flip = evt.target.hasAttribute('checked') ? 'removeAttribute' : 'setAttribute';
    evt.target[flip]('checked', 'checked');
    Request.PUT('edit/update.xml', this).then( result => {
      this.innerHTML = result.documentElement.innerHTML;
    });
  }
});
```

```style

section[id$=tasks] ul {
  list-style:none;
  padding: 0;
}
section[id$=tasks] li ul {
  border-left: 1px dashed rgb(0 0 0 / 0.25);
  padding-left: 0.75rem;
  margin:0.25rem 0.25rem;
}

```
