?title Checklist
?publish 2


# Quickstart

## Startup Tasks

- [ ] run `bin/server` to start a localhost environment. Edit that file to specify the port directly
- [ ] edit `views/pages/index.php` to make changes (this readme is edited by default)
- [ ] create a new page in pages, add processing instructions to make it visible.


## Explore Tasks

- [ ] Change port in `bin/php`
- [ ] Create a new file in `view/pages/`
- [ ] make a syntax error in an ^^html^^ document
- [ ] Checkout out `index.php`, note example route and play around

## Development Tasks

This application is often used on the ^^AWS^^ Platform, particularly *lightsail* for the server host, and *S3* for object storage.

- [ ] Set up `data/config.ini` file with keys
- [ ] Configure ligtsail server (this is a large task)
- [ ] init a git repo and set up remote
- [ ] review deployment strategy

// insert ../README.md //style

``` script

document.querySelector('article').addEventListener('click', evt => {
  if (evt.target.nodeName == 'INPUT')
    Request.GET('pages/checklist.md').then(alert);
});

```