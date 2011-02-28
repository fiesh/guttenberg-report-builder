<?php

define('ABSATZ_LAENGE', 10);
define('ZEILEN_LAENGE', 16.6);
define('ZUSATZ', 0);
define('ZEILEN_MARGIN_OBEN', 95);
define('FUSSNOTEN_MARGIN_UNTEN', 75);
define('FUSSNOTEN_LAENGE', 14.4);
define('FUSSNOTEN_ABSATZ_LAENGE', 3.5);
define('HOEHE', 910);

define('PATH', 'web');

$whitelist = array('KomplettPlagiat', 'Verschleierung', 'HalbsatzFlickerei', 'ShakeAndPaste', 'ÜbersetzungsPlagiat', 'StrukturPlagiat', 'BauernOpfer', 'VerschärftesBauernOpfer');

require_once('FragmentLoader.php');
require_once('render.php');

function calcPositionZeile($zeile, $zeilen)
{
	$top = ZEILEN_MARGIN_OBEN;
	$sum = 0;
	for ($i = 0; $i < count($zeilen); ++$i) {
		$sum += $zeilen[$i];
		if ($zeile <= $sum) {
			return $top + ABSATZ_LAENGE * $i
			            + ZEILEN_LAENGE * ($zeile-1);
		}
	}

	// line number not in a defined paragraph
	return false;
}

function calcPositionFussnote($zeile, $fussnoten)
{
	$top = HOEHE - FUSSNOTEN_MARGIN_UNTEN + FUSSNOTEN_ABSATZ_LAENGE;
	foreach($fussnoten as $i) {
		$top -= $i * FUSSNOTEN_LAENGE;
		$top -= FUSSNOTEN_ABSATZ_LAENGE;
	}

	$sum = 0;
	for ($i = 0; $i < count($fussnoten); ++$i) {
		$sum += $fussnoten[$i];
		if ($zeile <= $sum) {
			return $top + FUSSNOTEN_ABSATZ_LAENGE * $i
			            + FUSSNOTEN_LAENGE * ($zeile-1);
		}
	}

	// line number not in a defined paragraph
	return false;
}

function calcPosition($linenumber, $linetableEntry, $offset)
{
	if($linenumber > 100) { // Fussnote
		$pos = calcPositionFussnote($linenumber-100, $linetableEntry['fussnoten']);
		if ($pos === false)
			return false;
		return $pos + $offset * FUSSNOTEN_LAENGE;
	} else {
		$pos = calcPositionZeile($linenumber, $linetableEntry['zeilen']);
		if ($pos === false)
			return false;
		return $pos + $offset * ZEILEN_LAENGE;
	}
}

function calcExtents($fp, $fl, $linetable, $manualpos)
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

	// calculate start position and length
	if(isset($manualpos[$pagenum][$firstline][$lastline])) {
		$manualposEntry = $manualpos[$pagenum][$firstline][$lastline];
		$startpos = HOEHE * $manualposEntry['startpos'];
		$length = HOEHE * $manualposEntry['length'];

	} else if(isset($linetable[$pagenum])) {
		$linetableEntry = $linetable[$pagenum];

		$startpos = calcPosition($firstline, $linetableEntry, 0);
		$endpos = calcPosition($lastline, $linetableEntry, 1);

		if ($startpos === false)
			print "Fragment $fp $fl: Fehler, Zeile $firstline liegt nicht in einem definierten Absatz!\n";
		if ($endpos === false)
			print "Fragment $fp $fl: Fehler, Zeile $lastline liegt nicht in einem definierten Absatz!\n";
		if ($startpos === false || $endpos === false)
			return false;

		$length = $endpos - $startpos;

	} else {
		print "Fragment $fp $fl: Keine Zeilenangaben fuer Seite $pagenum!\n";
		return false;
	}

	// enlarge extents by ZUSATZ on both sides, round to nearest integers
	$extents['startpos'] = (int) round($startpos - ZUSATZ);
	$extents['length'] = (int) round($length + 2*ZUSATZ);

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
		# (== headers ==, # comments, <pre>, </pre>, [[Kategorie:...]], empty lines)
		if(!(preg_match('/^\s*(==.*==|#.*|<\/?pre>|\[\[Kategorie:\w+\]\])?\s*$/', $line))) {
			$result[] = $line;
		}
	}
	return $result;
}

function createLineNumberTable()
{
	foreach(getWikitextPayloadLines("Zeilenanzahl/Rohdaten") as $line) {
		preg_match('/^\s*(\d+):(\d+):([\d,]*):([\d,]*)\s*$/', $line, $a);
		$linetable[(int) $a[1]]['zeilen'] = explode(',', $a[3]);
		$linetable[(int) $a[1]]['fussnoten'] = explode(',', $a[4]);
	}
	return $linetable;
}

function createManualPositionTable()
{
	foreach(getWikitextPayloadLines("Visualisierungen/ReportBuilderManualPosition") as $line) {
		preg_match('/^\s*Fragment[ _](\d+)[ _](\d+)-(\d+)\s+(\d+(\.\d+)?)%?\s+(\d+(\.\d+)?)%?\s*$/i', $line, $a);
		$pagenum = (int) $a[1];
		$firstline = (int) $a[2];
		$lastline = (int) $a[3];
		$manualpos[$pagenum][$firstline][$lastline]['startpos'] = (double) $a[4] / 100.0;
		$manualpos[$pagenum][$firstline][$lastline]['length'] = (double) $a[6] / 100.0;
	}
	return $manualpos;
}

system('mkdir -p '.PATH.'/plagiate');

$linetable = createLineNumberTable();
$manualpos = createManualPositionTable();

$fragments = FragmentLoader::getFragments();

$i = 0;
foreach($fragments as $f) {
	if(!in_array($f[7], $whitelist)) {
		print "Fragment $f[1] $f[2]: Ignoriere, Plagiatstyp '$f[7]'\n";
	} else {
		$extents = calcExtents($f[1], $f[2], $linetable, $manualpos);
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

