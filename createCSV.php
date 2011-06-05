<?php

$whitelist = array('KomplettPlagiat', 'Verschleierung', 'HalbsatzFlickerei', 'ShakeAndPaste', 'ÜbersetzungsPlagiat', 'StrukturPlagiat', 'BauernOpfer', 'VerschärftesBauernOpfer');

require_once('FragmentLoader.php');

function quoteForCSV($s) {
	$s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
	$s = str_replace(array("\n", "\r"), array('&#10;', '&#13;'), $s);
	if (strpos($s, ',') !== false)
		$s = '"'.$s.'"';
	return $s;
}

function replaceCategoryLinks($s) {
	return preg_replace('/\[\[:Kategorie:([^\]\|]+)(\|[^\]]*)?\]\]/', 'http://de.guttenplag.wikia.com/wiki/Kategorie:$1', $s);
}

$ret = '';
$fragments = FragmentLoader::getFragments();

foreach($fragments as $f) {
	if(in_array($f[7], $whitelist)) {
		$f[10] = replaceCategoryLinks($f[10]);
		$first = true;
		for($i = 1; $i < 12; $i++) { 
			if(!$first)
				$ret .= ',';
			else
				$first = false;
			$ret .= quoteForCSV($f[$i]);
		}
		$ret .= "\n";
	}
}

echo 'Seite,Zeilen,TextDissertation,SeiteFundstelle,ZeilenFundstelle,TextFundstelle,Kategorie,ImLiteraturverzeichnis,Quelle,URL,Anmerkung'."\n";

echo $ret;
