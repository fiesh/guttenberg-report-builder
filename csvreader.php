<?php

class CSVUtil {
	static function read($filename) {
		$lines = explode("\n", file_get_contents($filename));
		$data = array();
		foreach ($lines as $line) {
			if (trim($line) != "" && $line[0] != ";") {
				$data[] = explode("\t", $line);
				
			}
		}
		return $data;
	}
	
	static function write($filename, $arr) {
		$lines = array();
		foreach ($arr as $row) {
			$lines[] = implode("\t", $row);
		}
		file_put_contents($filename, implode("\r\n", $lines));
	}
}