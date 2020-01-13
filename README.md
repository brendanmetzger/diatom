# Diatom

## Philosophy

I was going through old hard drives and organizing past work and I realized my first websites were from over 15 years ago. In the intervening years I had learned backend programming, used all the databases, frameworks, api's and platforms out there. But what struck me, was that anything done between 12 and 3 years ago is essentially non-functional without serious maintenance, and I certainly don't have the energy to install an outdated ruby to run a very outdated rails. But the stuff from 15 years ago is *perfectly intact* because it was just using html, css, and javascript in an organized file structure. Granted, it took me a lot of keystrokes to get the final output, but at least those keystrokes aren't gone. I spent years and years working on a few projects that I am still very proud of today under the full realization that I will never see or *use* them ever again, which is a bummer, because some of the interactive programs make the effort of that 15 year old work look like a walk in the park!

The ultimate goal is to **build heavier, more interesting, more robust websites,** that can almost function as applications, but are really just similar to the websites of yesterday. They can be trivial, exploratory, narrative-based, utilize data, and any number of other goals that might not require logins and cron jobs.

- against my better instincts, make things as simple as possible (avoid custom configs, flexible architecture, fringe use cases)
- sites created in this platform will be viewable 15 years from now
- provide methods organizing and creating content for user experience designers
- there is only one file with a backend language (hence diatom)
- content is authored by creating and organizing new html files (templates are html)
- some custom programming and data utilization can be configured in routing callbacks
- the name, brand, and initial conditions will melt away quickly, leaving an independant and unique creation to live on.
- respect relative file structure, that is, decrease reliance on absolute paths.



## Caveats

A pure html site is rather burdonsome to create, and probably precludes doing certain things of interest, notably working with data. As such, the templating system is rather strict, and enforces perfection.

- PHP at 7.2 or above.
- Must author strict xHTML (that's XML, essentially)
- content is assumed to have .html extension if unspecified
- the assumed layout is derived from index.html
- html files are stored in a directory called pages

All of the above can, of course, be changed—but you wouldn't change a configuration file, you'd re-author parts of the program.

## TODO

[ ] Consider abandoning namespaces