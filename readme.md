
# Pre Dispatcher #


## Description ##

The preDispatcher is a cache in front of a slow website delivery/ cms. Initially it was created for my website with Concrete5. But it could be used for other products too.

In my use case Concrete5 needed about 1.5 sec for a non cached page ... and still 200 ms to deliver a full page cached content. The footprint for the bootstrap process is quite high. This pre-dispatcher catches a request and puts it to a cache. It deliveres the local cahce file for a given time. So delivering the content starts in 1 ms (istead of 200 ms).

Author: Axel Hahn


## Licence ##

This is free software and Open Source 
GNU General Public License (GNU GPL) version 3


## Requirements ##

PHP 7


## Installation ##

### General instruction ###

* Extract the files in any wanted directory of your webroot
* in your main dispatcher add an include to the [path/predispatcher]/index.php
* go to [path/predispatcher]
* copy pre_dispatcher_config.dist.php to pre_dispatcher_config.php
* make your changes in the created config
  * In the beginning enable the debugging for your ip and enable html output
  * go to the ttl section and set the caching time for your requests. You can set a default and then override it by adding a list of regex to requests and its wanted caching times
  * if you have a CMS or other backend: you can define how to detect that a backend is open. Set a cookie variable name or session variable name in the __delcache__ section. This will delete a cache for an opened page if you are logged in.
  * The __nocache__ section - a request won't be stored in the cache if one of its conditions was found

### Instructions for Concrete5 ###

This pre-dispatcher was tested for public only pages that have no user specific information in the response.
The config dist file is prepared to detect a login to the C5 backend by a cookie named "CONCRETE5_LOGIN" in the delcache section. 

* Extract the files below [webroot]/application/pre_dispatcher/
* in the [webroot]/index.php add an include to the pre_dispatcher/index.php:

``` php
<?php
@include 'application/pre_dispatcher/index.php';
require 'concrete/dispatcher.php';
```

* go to [webroot]/application/pre_dispatcher/
* copy pre_dispatcher_config.dist.php to pre_dispatcher_config.php
* make your changes in the created config


## Config entries ##


### Default entries ###

This is the dist file:

``` php
<?php
	return array(
		'cache'=>array(

			// cache directory
			'dir'=>__DIR__.'/../../.ht_static_cache',

			//  generate readable filenames for cachefiles
			'readable'=>true,
		),

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
	);

```