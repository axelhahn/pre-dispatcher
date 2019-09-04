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
			'^/some/other/pages/.*'=>60*60,
			'^/my/more/dynamic/page$'=>60*15,

			// disallow all GET parameters
			'\?'=>0,

			// but allow params "item" and "id"
			'\?.*(item|id)\=[a-zA-Z0-9\_\-\%]*$'=>60*60*24*14,

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
