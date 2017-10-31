<?php 

	use voku\helper\HtmlMin;

	class Minifier {

		public function minify($file) {

				ob_start();
				require_once($file);
				$html = ob_get_clean();

				//Instantiating minifier
				$minifier = new HtmlMin();
				$html = $minifier->minify($html);

				return $html;

		}

	}

?>