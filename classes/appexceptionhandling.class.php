<?php

class AppExceptionHandling {

	public static function doException($exception){
		throw new Exception("Something messed up.");
	}
}

?>