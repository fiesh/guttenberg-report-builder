<?php

define('ZEILEN_LAENGE', 16.6);
define('FUSSNOTEN_LAENGE', 14.4);
define('ZUSATZ_OBEN', 2);
define('ZUSATZ_UNTEN', 0);
define('HOEHE', 910);

define('PATH', 'web');

$whitelist = array('KomplettPlagiat', 'Verschleierung', 'HalbsatzFlickerei', 'ShakeAndPaste', 'ÜbersetzungsPlagiat', 'StrukturPlagiat', 'BauernOpfer', 'VerschärftesBauernOpfer');

require_once('FragmentLoader.php');
require_once('render.php');

function calcPosition($linenumber, $linepositionsEntry, $offset)
{
	if($linenumber > 100) { // Fussnote
		$subentry = $linepositionsEntry['fussnoten'];
		$subindex = $linenumber - 101;
		$offsetheight = FUSSNOTEN_LAENGE;
	} else {
		$subentry = $linepositionsEntry['zeilen'];
		$subindex = $linenumber - 1;
		$offsetheight = ZEILEN_LAENGE;
	}

	if(isset($subentry[$subindex])) {
		return HOEHE * $subentry[$subindex] / 100.0
			+ $offset * $offsetheight;
	} else {
		// Zeile existiert nicht
		return false;
	}
}

function calcExtents($fp, $fl, $linepositions)
{
	// parse page number
	if (preg_match('/^\s*(\d+)\s*$/', $fp, $a)) {
		$pagenum = (int) $a[1];
	} else {
		print "Fragment $fp $fl: Fehlerhaftes Feld 'Seiten Dissertation'!\n";
		return false;
	}

	// parse first and last line
	if(preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $fl, $a)) {
		$firstline = (int) $a[1];
		$lastline = (int) $a[2];
	} else if (preg_match('/^\s*(\d+)\s*$/', $fl, $a)) {
		$firstline = (int) $a[1];
		$lastline = (int) $a[1];
	} else {
		print "Fragment $fp $fl: Fehlerhaftes Feld 'Zeilen Dissertation'!\n";
		return false;
	}
	if($firstline > $lastline) {
		print "Fragment $fp $fl: Startzeile ist > Endzeile!\n";
		return false;
	}

	// calculate start position and length
	if(isset($linepositions[$pagenum])) {
		$linepositionsEntry = $linepositions[$pagenum];

		$startpos = calcPosition($firstline, $linepositionsEntry, 0);
		$endpos = calcPosition($lastline, $linepositionsEntry, 1);
		$endpos2 = calcPosition($lastline+1, $linepositionsEntry, 0);

		if($startpos === false)
			print "Fragment $fp $fl: Fehler, Zeile $firstline liegt nicht in einem definierten Absatz!\n";
		if($endpos === false)
			print "Fragment $fp $fl: Fehler, Zeile $lastline liegt nicht in einem definierten Absatz!\n";
		if($startpos === false || $endpos === false)
			return false;

		if($endpos2 !== false)
			$endpos = max($endpos, $endpos2);

		$length = $endpos - $startpos;

	} else {
		print "Fragment $fp $fl: Keine Zeilenangaben fuer Seite $pagenum!\n";
		return false;
	}

	// enlarge extents by ZUSATZ on both sides, round to nearest integers
	$extents['startpos'] = (int) round($startpos - ZUSATZ_OBEN);
	$extents['length'] = (int) round($length + ZUSATZ_OBEN + ZUSATZ_UNTEN);

	if($extents['startpos'] + $extents['length'] > HOEHE) {
		print "Fragment $fp $fl liegt ausserhalb des Seitenbereichs!\n";
		return false;
	} else if($extents['length'] < 0) {
		print "Fragment $fp $fl hat negative Laenge!\n";
		return false;
	} else {
		return $extents;
	}
}

function prepare_png($pn, $num, $f)
{
	$cmd = 'convert images/'.$pn.'.png -crop 600x'.$f['length'].'+0+'.$f['startpos'].' -quality 100 -define png:bit-depth=8 '.PATH.'/plagiate/'.$pn.'_'.$num.'.png';

	system($cmd);
}

function getWikitextPayloadLines($pagetitle)
{
	$wikitext = explode("\n", file_get_contents('http://de.guttenplag.wikia.com/index.php?action=raw&templates=expand&title='.urlencode($pagetitle)));
	$result = array();
	foreach($wikitext as $line) {
		# remove comment lines
		# (== headers ==, # comments, <pre>, </pre>,
		# <nowiki>, </nowiki>, [[Kategorie:...]], empty lines)
		if(!(preg_match('/^\s*(==.*==|#.*|<\/?pre>|<\/?nowiki>|\[\[Kategorie:\w+\]\])?\s*$/', $line))) {
			$result[] = $line;
		}
	}
	return $result;
}

function createLinePositionTable()
{
	foreach(getWikitextPayloadLines("Zeilenpositionen") as $line) {
		if(preg_match('/^\s*(\d+):([\d.,]*):([\d.,]*)\s*$/', $line, $match)) {
			$pagenum = (int) $match[1];
			$linepositions[$pagenum]['zeilen'] = explode(',', $match[2]);
			$linepositions[$pagenum]['fussnoten'] = explode(',', $match[3]);
		} else {
			print "Syntaxfehler in wiki/Zeilenpositionen:\n";
			print "  '$line'\n";
		}
	}
	return $linepositions;
}

system('mkdir -p '.PATH.'/plagiate');

$linepositions = createLinePositionTable();

$fragments = FragmentLoader::getFragments();

$i = 0;
foreach($fragments as $f) {
	if(!in_array($f[7], $whitelist)) {
		print "Fragment $f[1] $f[2]: Ignoriere, Plagiatstyp '$f[7]'\n";
	} else {
		$extents = calcExtents($f[1], $f[2], $linepositions);
		if ($extents) {
			$fr[$i]['pagenumber'] = $f[1];
			$fr[$i]['lines'] = $f[2];
			$fr[$i]['startpos'] = $extents['startpos'];
			$fr[$i]['length'] = $extents['length'];
			$fr[$i]['orig'] = $f[6];
			$fr[$i]['category'] = $f[7];
			$fr[$i]['inLit'] = $f[8];
			$fr[$i]['src'] = $f[9];
			$fr[$i]['url'] = $f[10];
			$fr[$i]['anmerkung'] = $f[11];
			$fr[$i]['seitefund'] = $f[4];
			$fr[$i]['zeilenfund'] = $f[5];
			$i++;
		}
	}
}

$fragments = array();

for($page = 1; $page <= 475; $page++) {
	$fragments = array();
	$page = sprintf('%03d', $page);
	$i = 0;
	foreach($fr as $f)
		if($f['pagenumber'] == $page) {
			prepare_png($page, $i++, $f);
			$fragments[] = $f;
		}

	$output = printout($fragments, $page);
	$file = fopen(PATH."/$page.html", 'w'); 
	fwrite($file, $output);
	fclose($file);
}

