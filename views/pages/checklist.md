?title Checklist
?publish 2


# Quickstart

Open [this document](${wrapper}?url=${info.path.url}) to amend and edit project tasks

## Startup Tasks

- [ ] specify a new port in `bin/server` if necessary
- [ ] run `bin/server` to start a localhost, opens site in chrome by default
- [ ] edit `views/pages/index.php` to make changes (this readme is imported by default)
- [ ] create a new page in pages, add processing instructions to make it visible in the nav


## Explore Tasks

- [ ] Change port in `bin/php`
- [ ] Create a new file in `view/pages/`
- [ ] make a syntax error in an ^^html^^ document
- [ ] Checkout out `index.php`, note example route and play around


## Development Tasks

This application is often used on the ^^AWS^^ Platform, particularly *lightsail* for the server host, and *S3* for object storage.

- [ ] Set up `data/config.ini` file with keys
- [ ] Configure ligtsail server (this is a large task)
  - [ ] install php, with xml, curl, mbstring
  - [ ] install apache
  - [ ] add group for web
- [ ] init a git repo and set up remote
- [ ] review deployment strategy

// insert ../README.md //style

``` script

document.querySelector('article').addEventListener('click', evt => {
  if (evt.target.nodeName == 'INPUT')
    Request.GET('pages/checklist.md').then(alert);
});

```