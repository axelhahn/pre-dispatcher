
# Pre Dispatcher :: refresh #

[home](../readme.md)

The behaviour of a cache you expect is:
As long a cached page is not outdated then the cached content will be delivered.
If the moment comes that the ttl is reached then the cached content is outdated. The next request is uncached and creates a newly generated cache.

## Automatic cache refresh ##

`scipts/refresh.php`

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
		(...)
		'refreshcache'=>array(
			// a get variable name
			'get'=>array(
				'rebuildcache',
			),
		),
		(...)
```
Step b)
If you changed the content then make a request to the page to update and add your GET parameter, i.e.

    https://www.example.com/my/updated/page?rebuildcache=1

