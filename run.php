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
	$pos = ZEILEN_MARGIN_OBEN;
	$count = $zeilen[0];
	$i = 1;
	while($count < $zeile) {
		$pos += ABSATZ_LAENGE;
		if(!isset($zeilen[$i]) || $zeilen[$i] === 0) {
			print "Fehler, Zeile $zeile liegt nicht in einem definierten Absatz!\n";
			break;
		}
		$count += $zeilen[$i++];
	}

	return $pos + ZEILEN_LAENGE * ($zeile-1);
}

function calcPositionFussnote($zeile, $fussnoten)
{
	$pos = HOEHE - FUSSNOTEN_MARGIN_UNTEN + FUSSNOTEN_ABSATZ_LAENGE;
	foreach($fussnoten as $i) {
		$pos -= $i * FUSSNOTEN_LAENGE;
		$pos -= FUSSNOTEN_ABSATZ_LAENGE;
	}

	$count = $fussnoten[0];
	$i = 1;
	while($count < $zeile) {
		$pos += FUSSNOTEN_ABSATZ_LAENGE;
		if(!isset($fussnoten[$i]) || $fussnoten[$i] === 0) {
			print "Fehler, Fussnote $zeile liegt nicht in einem definierten Absatz!\n";
			break;
		}
		$count += $fussnoten[$i++];
	}

	return $pos + FUSSNOTEN_LAENGE * ($zeile-1);
}

function calcExtents($pagenum, $firstline, $lastline, $linetable, $manualpos)
{
	if(isset($manualpos[$pagenum][$firstline][$lastline])) {
		$manualposEntry = $manualpos[$pagenum][$firstline][$lastline];
		$startpos = HOEHE * $manualposEntry['startpos'];
		$length = HOEHE * $manualposEntry['length'];
	} else {
		$linetableEntry = $linetable[$pagenum];
		if($firstline > 100) // Fussnote
			$startpos = calcPositionFussnote($firstline-100, $linetableEntry['fussnoten']);
		else
			$startpos = calcPositionZeile($firstline, $linetableEntry['zeilen']);
		if($lastline > 100) // Fussnote
			$length = calcPositionFussnote($lastline-100, $linetableEntry['fussnoten']) + FUSSNOTEN_LAENGE - $startpos;
		else
			$length = calcPositionZeile($lastline, $linetableEntry['zeilen']) + ZEILEN_LAENGE - $startpos;
	}

	$extents['startpos'] = round($startpos - ZUSATZ);
	$extents['length'] = round($length + 2*ZUSATZ);
	return $extents;
}

function getFirstLastLine($z)
{
	if(preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $z, $a)) {
		return array((int) $a[1], (int) $a[2]);
	} else if (preg_match('/^\s*(\d+)\s*$/', $z, $a)) {
		return array((int) $a[1], (int) $a[1]);
	} else {
		return false;
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
	$pagenum = (int) $f[1];
	list($firstline, $lastline) = getFirstLastLine($f[2]);

	if(!in_array($f[7], $whitelist)) {
		print "Fragment $f[1] $f[2]: Ignoriere, Plagiatstyp '$f[7]'\n";
	} else if(!isset($linetable[$pagenum])) {
		print "Fragment $f[1] $f[2]: Keine Zeilenangaben fuer Seite $f[1]!\n";
	} else if(!$firstline || !$lastline) {
		print "Fragment $f[1] $f[2]: Fehlerhaftes Feld 'Zeilen Dissertation'!\n";
	} else {
		$extents = calcExtents($pagenum, $firstline, $lastline, $linetable, $manualpos);
		if ($extents['startpos'] + $extents['length'] <= HOEHE) {
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
		} else {
			print "Fragment $f[1] $f[2] liegt ausserhalb des Seitenbereichs!\n";
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

