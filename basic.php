<?php 

	class Basic {

		public function __construct() {

			//Loading configuration file
			$this->config = json_decode(file_get_contents(__DIR__ . '/config.json'));

		}

		public function respond($file, $params = false) {

			//Checking if we're talking about an index
			if ($file == '/' || $file == '\\') {
				foreach ($this->config->index as $index) {
					if (file_exists($this->config->www . '/' . $index)) {

						$file = '/' . $index;
						break;

					}
				}								
			}

			//Checking if the file exists, then checking if it's cached version is too old
			$referenceFile = $this->config->www . $file;
			if (file_exists($referenceFile)) {

				//Checking if we should cache-by-get
				if ($this->config->cacheByGet && count($params) > 0) {

					$cachedPath = 'cache/www' . $file . 'GET' . http_build_query($params);

				} else {

					$cachedPath = 'cache/www' . $file;

				}

				//Processing or serving the file
				if ($this->fileIsTooOld($cachedPath)) {

					$this->process($referenceFile, $cachedPath);
					
				} else {

					$this->serve($cachedPath);

				}

			} else {

				http_response_code(404);
				die(file_get_contents($this->config->{404}));

			}

		}

		public function process($file, $cachedPath = false) {

			if ($this->config->minify) {

				//Checking if there's a known minifier for this file by mime, then by extension
				$mimeToMatch = mime_content_type($file);
				$extensionToMatch = explode('.', $file);
				$extensionToMatch = end($extensionToMatch);

				$detectedMinifier = false;
				foreach ($this->config->minifiers as $key => $minifier) {
				
					//Itearting through every mime and attempting to find a match
					$count = count($minifier->mimes) - 1;
					for ($i = 0; $i <= $count; $i++) {
						if ($minifier->mimes[$i] == $mimeToMatch) {

							$detectedMinifier = $minifier;
							$detectedMinifier->minifierLocation = $key;
							break 2;

						}
					}

					//If nothing was found, then we attempt to find a matching extension
					$count = count($minifier->extensions) - 1;
					for ($i = 0; $i <= $count; $i++) {
						if ($minifier->extensions[$i] == $extensionToMatch) {

							$detectedMinifier = $minifier;
							$detectedMinifier->minifierLocation = $key;
							break 2;

						}
					}

				}

				//If a minifier was detected, then we minify the file and serve it
				if ($detectedMinifier !== false) {

					//Instantiating minifier and getting minified text
					require_once("minifiers/{$detectedMinifier->minifierLocation}.php");
					$minifier = new Minifier();
					$minifiedText = $minifier->minify($file);

					//Caching the file (if need be)
					if ($this->config->cache) {
	
						$cachedPath = ($cachedPath == false ? __DIR__ . '/cache/' . $file : $cachedPath);
						$this->verifyDirectories($cachedPath);

						file_put_contents($this->getBaseFile($cachedPath . $this->hashCookies(), true), $minifiedText);
						$this->serve($cachedPath, $detectedMinifier->mimes[0]);

					} else {

						$this->serve(NULL, $detectedMinifier->mimes[0], $minifiedText);

					}

				} else {

					$this->serve($file);

				}

			} else {

				$this->serve($file);

			}

		}

		public function serve($path, $mime = false, $text = false, $throw404 = false) {

			//Looking for the base file, throwing 404 if needed
			$path .= $this->hashCookies();
			$path = $this->getBaseFile($path);
			if ($path === false && $text === false) {
				if ($throw404) {

					//File not found, throwing a 404 if requested
					http_response_code(404);
					die(file_get_contents($this->config->{404}));

				} else {

					return false;

				}
			}

			//Sanitizing range if necessary
			if (isset($_SERVER['HTTP_RANGE'])) {

				$range = explode('=', $_SERVER['HTTP_RANGE']);
				$range = explode('-', $range[1]);

			} else {

				$range = false;

			}

			//Gathering content metadata and determining HTTP response code
			$response = array();
			$response['headers'] = array();
			if ($text !== false) {

				//Checking if a custom mime was requested
				if ($mime !== false) {

					$response['headers']['Content-Type'] = $mime;

				}

				//Preparing to serve the content
				$response['code'] = 200;
				$response['headers']['Content-Length'] = strlen($text);
				$response['content'] = $text;

			} else {
			
				//Checking if a custom mime was requested
				if ($mime !== false) {

					$response['headers']['Content-Type'] = $mime;

				} else {

					//MIME MAGIC IS A LIE!
					if (strpos($path, '.css') > 0) {

						$response['headers']['Content-Type'] = 'text/css';

					} else if (strpos($path, '.js') > 0) {

						$response['headers']['Content-Type'] = 'text/javascript';

					} else if (strpos($path, '.html') || strpos($path, '.php')) {

						$response['headers']['Content-Type'] = 'text/html';

					} else {

						//Letting PHP decide the default mime if we can't get it
						$mime = mime_content_type($path);
						if ($mime != 'text/plain') {
							
							$response['headers']['Content-Type'] = $mime;

						}

					}

				}
				
				//Executing the PHP file or acquiring the raw file
				if (strpos($path, '.php')) {

					//Running PHP file
					ob_start();
					require_once($path);
					$response['content'] = ob_get_clean();		
					$response['headers']['Content-Length'] = strlen($response['content']);
					$response['code'] = 200;

				} else {

					//Reading only the necessary portion of the file
					if ($range !== false) {

						$fileSize = filesize($path);
						$beginning = $range[0];
						$end = (!empty($range[1]) ? $range[1] : $fileSize);

						//Crafting response
						$response['code'] = 206;
						$response['headers']['Content-Length'] = (($end - $beginning));
						$response['headers']['Content-Range'] = 'bytes ' . $beginning . '-' . ($end - 1) . '/' . $fileSize;
						$response['content'] = '';

						//Reading the file
						$fh = fopen($path, 'rb');
						fseek($fh, $beginning, 0);
					
						$current = $beginning;
						while (!feof($fh) && $current <= $end && (connection_status() == 0)) {

							$response['content'] .= fread($fh, min(1026 * 16, ($end - $current)));
							$current += 1024 * 16;

						}

					} else {

						$response['code'] = 200;
						$response['content'] = file_get_contents($path);
						$response['headers']['Content-Length'] = filesize($path);

					}
					
				}

			}

			//Checking if we need to crop the response of a processed PHP file/text response
			if ($range !== false && $response['code'] == 200) {

				$beginning = $range[0];
				$end = (!empty($range[1]) ? $range[1] : $response['headers']['Content-Length']);

				$response['code'] = 206;
				$response['content'] = mb_strcut($response['content'], $beginning, $end);
				$response['headers']['Content-Length'] = strlen($response['content']);
				$response['headers']['Content-Range'] = 'bytes ' . $beginning . '-' . ($end - 1) . '/' . strlen($response['content']);

			}

			//Outputting the headers and the content
			http_response_code($response['code']);
			foreach ($response['headers'] as $header => $value) {

				header($header . ':' . $value);

			}

			die($response['content']);

		}

		private function hashCookies() {
			if ($this->config->cache && $this->config->cacheByCookies) {

				//Determining which cookies should be ignored (3.0753579139709)
				$cookies = $_COOKIE;
				$cookies =  array_flip($cookies);
		
				$cookiesToIgnore = array();
				foreach ($this->config->cookiesToIgnore as $pattern) {

					$cookiesToIgnore = array_merge(preg_grep("/{$pattern}/", $cookies), $cookiesToIgnore);

				}

				$cookies = array_flip($cookies);
				$cookiesToIgnore = array_keys(array_flip($cookiesToIgnore));
				
				//Removing those cookies from the equation
				foreach ($cookiesToIgnore as $cookie) {

					unset($cookies[$cookie]);

				}

				//Hashing the cookies and returning it
				return md5(json_encode($cookies));

			} else {

				return '';

			}
		}

		private function verifyDirectories($path) {

			//Standardizing slash direction then exploding string
			$path = str_replace('\\', '/', $path);
			$directoriesToCheck = explode('/', $path);
			array_pop($directoriesToCheck);

			//Checking if all requested directories exist
			$currentDir = $directoriesToCheck[0];
			array_shift($directoriesToCheck);
			foreach ($directoriesToCheck as $dir) {

				//Creating directory if it doesn't exists
				$currentDir .= '/' . $dir;
				if (!is_dir($currentDir) && !file_exists($currentDir)) {

					mkdir($currentDir);

				} else if (file_exists($currentDir) && !is_dir($currentDir)) {

					//Renaming base file, creating new folder, then moving it inside it
					rename($currentDir, $currentDir . '.tmp');
					mkdir($currentDir);
					rename($currentDir . '.tmp', $currentDir . '/' . $dir . '.base');

				}

			}

		}
		
		public function fileIsTooOld($file, $returnTime = false) {
			if (file_exists($file)) {

				$fileTime = filemtime($file);
				if ($returnTime == true) {

					return $fileTime;

				} else if ($fileTime < time() - $this->config->duration) {

					return true;

				}
			
			} else {

				return true;

			}

			return false;
		}

		private function getBaseFile($path, $returnPath = false) {
			if (file_exists($path)) {
				if (is_dir($path)) {

					return $path . '.base';

				} else {

					return $path;

				}
			} else {

				return ($returnPath ? $path : false);

			}
		}

	}

?>