<?php

require_once('FragmentLoader.php');

$ret = '';
$fragments = FragmentLoader::getFragments();

foreach($fragments as $f) {
	$first = true;
	for($i = 1; $i < 12; $i++) { 
		if(!$first)
			$ret .= ',';
		else
			$first = false;
		$ret .= $f[$i];
	}
	$ret .= "\n";
}

$file = fopen(CACHEFILE, 'w');
fwrite($file, $ret);
fclose($file);
echo $ret;
