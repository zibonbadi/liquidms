<?php
namespace LiquidMS;

require_once __DIR__.'/../../vendor/autoload.php';

class TimestampModel{

	private static $filename = __DIR__."/../../timestamps.yaml";
	private static $data = [];

	public static function init(string $filename = __DIR__."/../../timestamps.yaml"){

		if(!file_exists($filename)){
			yaml_emit_file($filename, []);
		}


		$import = yaml_parse_file($filename, -1);

		// Merge all yaml configs together
		$import_compose = [];
		foreach($import as $docname => $doc) {
			if(gettype($import_compose) == "array"){ $import_compose = array_merge_recursive($import_compose, $doc); }
		}

		self::setData($import_compose);
		self::$filename = $filename;
	}

	static function getData(){ return self::$data;}
	static function setData($newdata){self::$data = $newdata;}

	static function dumpData(String $filename = NULL){
		$writeTo = self::$filename;
		if($filename != NULL){ $writeTo = $filename; }
		yaml_emit_file($writeTo, self::$data);
	}
}

?>
