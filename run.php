<?php

chdir(dirname(__FILE__));

define('ZEILEN_LAENGE', 16.6);
define('FUSSNOTEN_LAENGE', 14.4);
define('ZUSATZ_OBEN', 2);
define('ZUSATZ_UNTEN', 0);
define('HOEHE', 910);
define('MIN_PAGE', 1);
define('MAX_PAGE', 475);

define('PATH', 'web');

require_once('BibliographyLoader.php');
require_once('FragmentLoader.php');
require_once('WikiLoader.php');
require_once('render.php');
require_once('renderindex.php');
require_once('rendersources.php');

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
			$endpos = min($endpos, $endpos2);

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

function makeAssociative($a)
{
	$result = array();
	foreach ($a as $b) {
		$result[$b['title']] = $b;
	}
	return $result;
}

function getIntersection($arr1, $arr2)
{
	$intersect = array_values(array_intersect($arr1, $arr2));
	return empty($intersect) ? false : $intersect[0];
}

// Anmerkung eines Fragments korrigieren
function korrFragmentAnmerkung($s)
{
	$s = trim($s);
	$s = preg_replace('/\{\{Fragmentsichter[^}]*\}\}/i', '', $s);
	//$s = korrStringWithLinks($s);
	return $s;
}


function prepare_png($pn, $num, $f)
{
	$cmd = 'convert images/'.$pn.'.png -crop 600x'.$f['length'].'+0+'.$f['startpos'].' -quality 100 -define png:bit-depth=8 '.PATH.'/plagiate/'.$pn.'_'.$num.'.png';

	system($cmd);
}

function getWikitextPayloadLines($pagetitle)
{
	$wikitext = explode("\n", WikiLoader::getRawTextByTitle($pagetitle));
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
	$title = 'GuttenPlag Wiki:Metadaten/Zeilenpositionen';
	foreach(getWikitextPayloadLines($title) as $line) {
		if(preg_match('/^\s*(\d+):([\d.,]*):([\d.,]*)\s*$/', $line, $match)) {
			$pagenum = (int) $match[1];
			$linepositions[$pagenum]['zeilen'] = explode(',', $match[2]);
			$linepositions[$pagenum]['fussnoten'] = explode(',', $match[3]);
		} else {
			print "Syntaxfehler in $title:\n";
			print "  '$line'\n";
		}
	}
	return $linepositions;
}

system('mkdir -p '.PATH.'/plagiate');

$fragmenttypes = makeAssociative(FragmentLoader::getFragmentTypes());
$sources = makeAssociative(BibliographyLoader::getSources());
$linepositions = createLinePositionTable();

// Lade Fragmente aus Wiki, Vorverarbeitung und Berechnung der Fragmentposition
$fr = array();
foreach(FragmentLoader::getFragmentsG2006() as $fragdata) {
	$title = $fragdata['wikiTitle'];
	$extents = calcExtents($fragdata[1], $fragdata[2], $linepositions);
	if($extents) {
		$f['page'] = $fragdata[1];
		$f['lines'] = $fragdata[2];
		$f['startpos'] = $extents['startpos'];
		$f['length'] = $extents['length'];
		$f['origpage'] = $fragdata[4];
		$f['origlines'] = $fragdata[5];
		$f['orig'] = $fragdata[6];
		$f['category'] = getIntersection(array_keys($fragmenttypes), $fragdata['categories']);
		$f['src'] = getIntersection(array_keys($sources), $fragdata['categories']);
		$f['url'] = $fragdata[10];
		$f['note'] = korrFragmentAnmerkung($fragdata[11]);
		if($f['category'] === false) {
			print "$title: keine Plagiatskategorie gefunden!\n";
		} else if($f['src'] === false) {
			print "$title: keine Quelle gefunden!\n";
		} else {
			$fr[] = $f;
		}
	}
}

// Verarbeitung der Quellen
foreach($sources as $title => $source) {
	$sources[$title]['rendered'] = renderSource($source, false);
	$sources[$title]['renderedplain'] = renderSource($source, true);
}

// Berechne minimale Hoehe der Originaltext-divs
// == min(Fragmenthoehe, naechste Startposition - aktuelle Startposition)
for($i = 0; $i < count($fr); ++$i) {
	$origlength = $fr[$i]['length'];
	for($j = 0; $j < count($fr); ++$j) {
		if($fr[$j]['page'] == $fr[$i]['page'] &&
		   $fr[$j]['startpos'] > $fr[$i]['startpos']) {
			$origlength = min($origlength,
				$fr[$j]['startpos'] - $fr[$i]['startpos']);
		}
	}
	$fr[$i]['origlength'] = $origlength;
}

// teile Plagiate nach Dissertationsseiten auf
$pagefrags = array();
for($page = 1; $page <= 475; $page++) {
	$pagefrags[$page] = array();
}
foreach($fr as $f) {
	$page = (int) $f['page'];
	if($page >= MIN_PAGE && $page <= MAX_PAGE) {
		$pagefrags[$page][] = $f;
	} else {
		print "$title: fehlerhaftes Feld 'Seiten Dissertation'!\n";
	}
}

// Gib ein HTML-Dokument pro Dissertationsseite aus, mit Fragmenten
for($page = MIN_PAGE; $page <= MAX_PAGE; $page++) {
	$pn = sprintf('%03d', $page);
	$i = 0;
	foreach($pagefrags[$page] as $f) {
		prepare_png($pn, $i++, $f);
	}

	$output = printout($pagefrags[$page], $sources, $pn);
	$file = fopen(PATH."/$pn.html", 'w'); 
	fwrite($file, $output);
	fclose($file);
}

// Gib index.html aus
$plagsPerPage = array();
for($page = MIN_PAGE; $page <= MAX_PAGE; $page++) {
	$pagesources = array();
	foreach($pagefrags[$page] as $f) {
		if (!in_array($f['src'], $pagesources))
			$pagesources[] = $f['src'];
	}
	$plagsPerPage[$page] = count($pagesources);
}
$output = printout_index($plagsPerPage);
$file = fopen(PATH."/index.html", 'w'); 
fwrite($file, $output);
fclose($file);
