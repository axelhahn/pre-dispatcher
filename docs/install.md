
# Pre Dispatcher :: installation #

[home](../readme.md)

## Instructions for Concrete5 ##

It acts like a full page cache and is placed before any C5 bootstrap action. This is why it is fast.
This pre-dispatcher was tested for public only pages (that have no user specific content in the response).

* Extract the files below [webroot]/application/pre_dispatcher/
* in the [webroot]/index.php add an include line to the pre_dispatcher/index.php:

``` php
<?php
@include 'application/pre_dispatcher/index.php';
require 'concrete/dispatcher.php';
```

* go to [webroot]/application/pre_dispatcher/
* copy pre_dispatcher_config.dist.php to pre_dispatcher_config.php
* make your changes in the created config
* Enable the refresh cronjob
