
# Pre Dispatcher #


## Description ##

The preDispatcher is a cache in front of a slow website delivery/ cms. Initially it was created for my website with Concrete5. But it could be used for other products too.
It is written for websites running on a shared hosting where people cannot install caching services like varshish.

To see the real life example: go to https://www.axel-hahn.de/ and navigate around.

In my use case Concrete5 needed about 1.5 sec for a non cached page ... and still 200 ms to deliver a website with enabled "full page" cached content. The footprint for the bootstrap process is quite high. This pre-dispatcher catches a request and puts it to a cache. It deliveres the local cache file for a given time. So delivering the content starts in 1 ms (instead of 200 ms).

Author: Axel Hahn
Source: https://github.com/axelhahn/pre-dispatcher


## Status ##

ALPHA ... it is used on my own website. 
I need to abstract it to make it usable for other tools.


## Licence ##

This is free software and Open Source 
GNU General Public License (GNU GPL) version 3


## Requirements ##

PHP 7
Means: just plain PHP - no database, no special modules.


## Features ##

* It is a fast full page cache for GET requests to handle slow backends. Other request methods won't be cached.
* Caching of pages with GET parameters (you need to define exclusions of variable names if not to cache them)
* Minimal requirements: it can be used on any shared hoster (it just needs file access - no database, no other service or module)
* automatic deletion of cached entries (if you are in a backend: define cookie or session variable names to delete a page cache)
* Delete or touch a single file to flush all older cache entries (i.e. for changes in a layout template)
* force the refresh of a cached content
* debugging features to follow the behaviour:
   * enable/ disable debugging
   * limit visibility of debug infos to defined ip addresses
   * write debug into http response header and/ or as html code


## Installation ##

### Instructions for Concrete5 ###

It acts like a full page cache and is placed before any C5 bootstrap action. This is why it is fast.
This pre-dispatcher was tested for public only pages (that have no user specific content in the response).

The config dist file is prepared to detect a login to the C5 backend by a cookie named "CONCRETE5_LOGIN" in the delcache section. 

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

## Config entries ##


### Default entries ###

This is the dist file:

``` php
<?php
	return array(

		'debug'=>array(

			// enable debug?
			'enable'=>true,

			// how to send debug infos
			'header'=>true,
			'html'=>true,

			// limit debug output to given ips
			'ip'=>array(
				'127.0.0.1',
			),
		),

		// caching ttl values
		'ttl'=>array(

			// default caching time in [s]
			'_default'=>60*60*24*14,

			// regex of request uri to override a default
			'^/my/more/dynamic/page$'=>60*60,
			'^/some/nother/pages/.*'=>60*15,
		),

		// ------------------------------------------------------------
		// force deletion of a cache item (and also do not cache) 
		// if I find ...
		'delcache'=>array(

			// a cookie variable name
			'cookie'=>array(
				'CONCRETE5_LOGIN'
			),

			// a session variable name
			'session'=>array(),

			// a get variable name
			'get'=>array(),

			// text/ regex in content
			'body'=>array(
				'Page Not Found',
			),
		),
		// ------------------------------------------------------------
		// do not cache if (a "delcache" rule matches or) I find ...
		'nocache'=>array(

			// a cookie variable name
			'cookie'=>array(),

			// a session variable name
			'session'=>array(),

			// a get variable name
			'get'=>array(
				'nocache',
			),

			// text/ regex in content
			'body'=>array(
				'<b>Notice</b>:.*on line <b>',
				'<b>Warning</b>:.*on line <b>',
			),
		),
		// ------------------------------------------------------------
		// ignore existing cache and store a cached version if I find ...
		'refreshcache'=>array(
			// a get variable name
			'get'=>array(
				'rebuildcache',
			),
		),
	);

```

## Maintenance ##

### Cleanup old cache files ###

To remove old cache files start the cleanup.php on command line.
It deletes files below .ht_static_cache older 14 days. 

Recommendation:
Run it once a week.

TDOD: time needs to be a configured value


### Automatic cache refresh ###

The behaviour of a cache you expect is:
As long a coched page is not outdated then the cached content will be delivered.
If the moment comes that the ttl is reached then the cached content is outdated. The next request is uncached and creates a newly generated cache.

Everytime you start the refresh.php it ...
* reads all entries in the cache detecting those having a lifetime less 4 h
* makes an http request that replaces the existing cache with the content of the last request

If you can imagine timing is everything.

**Recommendation I:**
If you have static pages with a ttl of a couple of days, then run the refresh.php several times per day or once per hour.

**Recommendation II:**
If you have pages that are updated by a script (i.e. you show news and update the news page every 10 min) then you can trigger request to a refresh its cached content directly.
To use a GET parameter named "rebuildcache" then ...

Step a)
Add it in the config to refreshcache -> get

``` php
		'refreshcache'=>array(
			// a get variable name
			'get'=>array(
				'rebuildcache',
			),
		),
```
Step b)
If you changed the content then make a request to the page to update and add your GET parameter, i.e.

    https://www.example.com/my/updated/page?rebuildcache=1

