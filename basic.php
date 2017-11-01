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
						
						file_put_contents($cachedPath, $minifiedText);
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

		public function serve($path, $mime = false, $text = false) {
			if ($text !== false) {

				//Checking if a custom mime was requested
				if ($mime !== false) {
					header('Content-Type:' . $mime);
				}

				//Serving the file
				http_response_code(200);
				header('Content-Length:' . strlen($text));
				echo($text);

				die();

			} else {
			
				//Checking if a custom mime was requested
				if ($mime !== false) {

					header('Content-Type:' . $mime);

				} else {

					//MIME MAGIC IS A LIE!
					if (strpos($path, '.css') > 0) {

						header('Content-Type: text/css');

					} else if (strpos($path, '.js') > 0) {

						header('Content-Type: text/javascript');

					} else if (strpos($path, '.html') || strpos($path, '.php')) {

						header('Content-Type: text/html');

					} else {

						//Letting PHP decide the default mime if we can't get it
						$mime = mime_content_type($path);
						if ($mime != 'text/plain') {
							
							header('Content-Type: ' . mime_content_type($path));

						}

					}

				}
				
				//Serving the file
				http_response_code(200);
				header('Content-Length:' . filesize($path));
				readfile($path);

				die();

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
				if (!is_dir($currentDir)) {

					mkdir($currentDir);

				}		

			}

		}
		
		public function fileIsTooOld($file) {
			if (file_exists($file)) {
				if (filemtime($file) < time() - $this->config->duration) {
					return true;
				}
			} else {
				return true;
			}

			return false;
		}

	}

?>