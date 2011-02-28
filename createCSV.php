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

echo 'Seite,Zeilen,TextDissertation,SeiteFundstelle,ZeilenFundstelle,TextFundstelle,Kategorie,ImLiteraturverzeichnis,Quelle,URL,Anmerkung'."\n";

echo $ret;
