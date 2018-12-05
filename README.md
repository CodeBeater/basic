![Basic's logo](https://i.imgur.com/Uo0imPB.png)

###Simple web caching engine, by and for PHP (WIP).

Philosophy
---
Coding websites is a hard and tricky process. Writing efficient and scalable projects is an ever harder process as applications get more and more complex, therefore, code efficiency is now considered a high-value asset.

This tool seeks to facilitate the creation of such code, by gracefully not repeating computations wherever possible, through caching.

Why Basic?
---
We believe that caching should not be a black box approach, and that amazing gains in response time and efficiency can be achieved if such process is left at the hands of the programmer.

Basic was built from the ground up to give developers maximum control over caching procedures, allowing them to select exactly what, when and how content should be cached.

You may cache everything, or just one module of a single route, it's up to you!

Getting started
---
**Composer installation**

```
Soon. We are currently waiting for 1.0.0 (scheduled Q2 2019).
```

**How it works**

There are two modes you can use Basic, automatic and manual.

In the manual mode, Basic will generate a file and cache it on the path specified by the user.

```php
<?php 

	require_once('vendor/autoload.php');

	$b = new Basic();

	//Usage with a pseudo-router
	$router->respond('GET', '/user/info', function() {

		$cachedFilePath = 'cache/user/info/' . $_GET['id']; //The path where the .html will be stored
		if ($b->fileIsTooOld($cachedFilePath)) {

			//Interpreting the given PHP file in the current scope, generating and saving .html cache file.
			$content = $b->process('controllers/user/info.php');
			die($content);

		} else {

			//If the file isn't too old, then we just serve the cached file itself
			$b->serve($cachedFilePath);

		}

	});

?>
```

Usage of the automatic mode, at this point and time is highly discouraged, as the feature is still on it's early stages of implementation.

Configuration
---
All of Basic's configuration can be found in ```config.json```, but you can also hot swap settings on the go.

```php
<?php 

	require_once('vendor/autoload.php');

	$b = new Basic();

	//The cache files for this endpoint expires in 60 seconds
	$router->respond('GET', '/user/info', function() {

		$b->config->duration = 60;
		$cachedFilePath = 'cache/user/info/' . $_GET['id'];
		if ($b->fileIsTooOld($cachedFilePath)) {

			$content = $b->process('controllers/user/info.php');
			die($content);

		} else {

			$b->serve($cachedFilePath);

		}

	});

	//But the cache files for this endpoint expires in 300 seconds
	$router->respond('GET', '/user/preferences', function() {

		$b->config->duration = 300;
		$cachedFilePath = 'cache/user/preferences/' . $_GET['id'];
		if ($b->fileIsTooOld($cachedFilePath)) {

			$content = $b->process('controllers/user/preferences.php');
			die($content);

		} else {

			$b->serve($cachedFilePath);

		}

	});

?>
```

**config.json fields**

- ```cache``` Weather or not the caching engine is active. Setting this to false will make it so the page is processed normally, but isn't stored on disk.
- ```duration``` The lifetime in seconds of a cache file. Will cause ```fileIsTooOld()``` to retun true after such time has elapsed.
- ```minify``` Weather or not to attempt minification on a given file. Failure to find a valid minifier will cause the file to be served and cached as-is.
- ```cacheByGet``` Weather or not to consider GET (not RESTful) parameters on an automatic or internal ```respond``` call. Leave as is.
- ```cacheByCookie``` Weather or not cookies should be considered when looking for a valid cached file.
- ```cookiesToIgnore``` An array of cookies that should be disconsidered when looking for an user-specific cached file. We recoomend setting tracking cookies and the likes to be ignored here. 
- ```www``` WIP. Will be used in the future in the automatic mode
- ```404``` WIP. Will be used in the future in the automatic mode
- ```minifiers``` An object containing mimes and extensions of files that have minification support (mimes are considered first).


Logo icon credits:

storage by Brandon Gamm from the Noun Project