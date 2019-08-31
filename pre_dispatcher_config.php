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
				'77.58.182.34',
				'130.92.79.49',
			),
		),

		// caching ttl values
		'ttl'=>array(

			// default caching time in [s]
			'_default'=>0,

			// a default with whitelisting
			'^/(startseite|batch|projects|music|kiste|suche|login)[a-zA-Z0-9\?\&\%\_\-\=\/\.]*'=>60*60*24*14,

			// regex of request uri to override a default
			'^/startseite$'=>60*60,
			'^/kiste/rss-news/[a-z\-]*'=>60*60,
			'^/kiste/werkzeuge/[a-z\-]*'=>0,
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
				'Die\ Seite\ konnte\ nicht\ gefunden\ werden\.',
				'Page Not Found',
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
				'remark_',
				'nocache',
			),

			// text/ regex in content
			'body'=>array(
				'<b>Notice</b>:.*on line <b>',
				'<b>Warning</b>:.*on line <b>',
			),
		),
		'refreshcache'=>array(
			// a get variable name
			'get'=>array(
				'remark',
				'rebuildcache',
			),
		),
	);
