<?php

function processString($s)
{
	$needle = '';
	for($i = 1; $i < 12; $i++)
		$needle .= 'val_'.$i.'="([^"]*)"\s+';
	preg_match_all("/$needle/", $s, $a);
	for($i = 1; $i < 12; $i++) {
		$a[$i] = trim($a[$i][0]);
		if(strpos($a[$i], ',') !== false)
			$a[$i] = '"'.$a[$i].'"';
	}
	return $a;
}

function getPrefixList($prefix)
{
	return unserialize(file_get_contents('http://de.guttenplag.wikia.com/api.php?action=query&prop=revisions&&format=php&generator=allpages&gaplimit=500&gapprefix='.urlencode($prefix)));
}

function getEntries($pageids)
{
	return unserialize(file_get_contents('http://de.guttenplag.wikia.com/api.php?action=query&prop=revisions&rvprop=content&format=php&pageids='.urlencode($pageids)));
}


echo 'Seite,Zeilen,TextDissertation,SeiteFundstelle,ZeilenFundstelle,TextFundstelle,Kategorie,ImLiteraturverzeichnis,Quelle,URL,Anmerkung'."\n";

$ret = '';
$polls =  getPrefixList('Fragment ');

$i = 0;
$pageids = '';
$fragments = array();
foreach($polls['query']['pages'] as $page) {
	$pageids .= $page['pageid'].'|';
	if(++$i === 49) {
		$i = 0;
		$entries = getEntries($pageids);
		$fragments = array_merge($fragments, $entries['query']['pages']);
		$pageids = '';
	}
}
$entries = getEntries($pageids);
$fragments = array_merge($fragments, $entries['query']['pages']);

foreach($fragments as $f) {
	$first = true;
	$a = processString($f['revisions'][0]['*']);
	if(isset($a[0]) && $a[0]) {
		for($i = 1; $i < 12; $i++) { 
			if(!$first)
				$ret .= ',';
			else
				$first = false;
			$ret .= $a[$i];
		}
	$ret .= "\n";
	}
}

$file = fopen(CACHEFILE, 'w');
fwrite($file, $ret);
fclose($file);
echo $ret;
