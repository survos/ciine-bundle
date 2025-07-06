# CiineBundle

Symfony Bundle that provides some utilities for using asciinema 3.0 to create asciiCasts (aka ciineCasts)
 
# Install asciinema

# Install the bundle

# Configure .bashrc

The easiest way to use this bundle is to pre-configure a few commands.  You can also run everything manually, but it's a bit of a pain to track the files.

# Usage

Start the recorder.  

```bash
rec "Create the User Entity"
bin/console make:user
```

end with <ctrl>-D.

There are some special commands during the recording process.

```bash
composer req easyadmin
bin/console make:crud:dashboard
bin/console debug:route easyadmin
bin/console ciine:screenshot /admin
bin/console ciine:copy .env.local
```
