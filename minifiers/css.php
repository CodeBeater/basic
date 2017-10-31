<?php 

	use MatthiasMullie\Minify;
	
	class Minifier {

		public function minify($file) {

			//Instantiating minifier
			$minifier = new Minify\CSS($file);

			//Minifiyng and saving
			return $minifier->minify();

		}

	}

?>