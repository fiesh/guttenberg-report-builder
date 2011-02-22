<?php

require_once('FragmentLoader.php');

$fragments = FragmentLoader::getFragments();

foreach($fragments as $f) {
	$first = true;
	preg_match_all('/\[\[Kategorie:([^]]+)\]\]/', $f[0], $matches);
	$categories = array_unique($matches[1]);
	preg_match_all('/(\d+)-(\d+)/', $f[2], $z);
	if(!isset($z[2][0]))
		$z[2][0] = $z[1][0];
	$start = $z[1][0];
	$ende = $z[2][0];
	$zeilen[$f[7]] += $ende - $start + 1;
	$fragmente[$f[7]]++;
	foreach($categories as $cat) {
		$zeilen[$cat] += $ende - $start + 1;
		$fragmente[$cat]++;
	}
}

foreach($zeilen as $c => $val) {
	print "Zeilen: $c: $val\n";
}
foreach($fragmente as $c => $val) {
	print "Fragmente: $c: $val\n";
}
