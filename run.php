<?php

define('ABSATZ_LAENGE', 10);
define('ZEILEN_LAENGE', 16.6);
define('ZUSATZ', 0);
define('ZEILEN_MARGIN_OBEN', 95);
define('FUSSNOTEN_MARGIN_UNTEN', 75);
define('FUSSNOTEN_LAENGE', 14.4);
define('FUSSNOTEN_ABSATZ_LANGE', 3.5);
define('HOEHE', 910);

function calcStartposFussnote($zeile, $fussnoten)
{
	$startpos = HOEHE - FUSSNOTEN_MARGIN_UNTEN + FUSSNOTEN_ABSATZ_LAENGE;
	foreach($fussnoten as $i) {
		$startpos -= $i * FUSSNOTEN_LAENGE;
		$startpos -= FUSSNOTEN_ABSATZ_LAENGE;
	}

	$pos = 0;
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

	return -$ZUSATZ + $startpos + (int) round(FUSSNOTEN_LAENGE * ($zeile-1) + $pos);
}

function calcStartposZeile($zeile, $zeilen)
{
	$pos = 0;
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

	return -ZUSATZ + (int) (ZEILEN_MARGIN_OBEN + round(ZEILEN_LAENGE*($zeile-1) + $pos));

}

function calcLengthFussnote($start, $ende, $fussnoten)
{
	$pos = 0;
	$count = $fussnoten[0];
	$i = 1;
	while($count < $start) {
		if(!isset($fussnoten[$i]) || $fussnoten[$i] === 0) {
			$count = false;
			break;
		}
		$count += $fussnoten[$i++];
	}
	if($count) {
		while($count < $ende) {
			$pos += FUSSNOTEN_ABSATZ_LAENGE;
			if(!isset($fussnoten[$i]) || $fussnoten[$i] === 0)
				break;
			$count += $fussnoten[$i++];
		}
	}
	return 2*ZUSATZ + (int) round(FUSSNOTEN_LAENGE * ($ende - $start + 1) + $pos);

}

function calcLengthZeile($start, $ende, $zeilen)
{
	$pos = 0;
	$count = $zeilen[0];
	$i = 1;
	while($count < $start) {
		if(!isset($zeilen[$i]) || $zeilen[$i] === 0) {
			$count = false;
			break;
		}
		$count += $zeilen[$i++];
	}
	if($count) {
		while($count < $ende) {
			$pos += ABSATZ_LAENGE;
			if(!isset($zeilen[$i]) || $zeilen[$i] === 0)
				break;
			$count += $zeilen[$i++];
		}
	}
	return 2*ZUSATZ + (int) round(ZEILEN_LAENGE * ($ende - $start + 1) + $pos);
}

function calcStartpos($z, $zeilen)
{
	preg_match_all('/(\d+)-(\d+)/', $z, $a);
	if($a[1][0] > 100) // Fussnote
		return calcStartposFussnote($a[1][0] - 100, $zeilen['fussnoten']);
	else
		return calcStartposZeile($a[1][0], $zeilen['zeilen']);
}

function calcLength($z, $zeilen)
{
	preg_match_all('/(\d+)-(\d+)/', $z, $a);
	if(!$a[2][0])
		$a[2][0] = $a[1][0];
	if($a[1][0] > 100) // Fussnote
		return calcLengthFussnote($a[1][0] - 100, $a[2][0] - 100, $zeilen['fussnoten']);
	else
		return calcLengthZeile($a[1][0], $a[2][0], $zeilen['zeilen']);
}

function prepare_png($pn, $num, $f)
{
	$cmd = 'convert images/'.$pn.'.png -crop 600x'.$f['length'].'+0+'.$f['startpos'].' -quality 100 -define png:bit-depth=8 web/plagiate/'.$pn.'_'.$num.'.png';

	system($cmd);
}

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

function createLineNumberTable()
{
	$file = fopen('zeilenanzahl', 'r');
	while(($line = fgets($file)) !== FALSE) {
		preg_match_all('/(\d+):(\d+):([\d,]+):([\d,]*)/', $line, $a);
		$r[(int) $a[1][0]]['zeilen'] = explode(',', $a[3][0]);
		$r[(int) $a[1][0]]['fussnoten'] = explode(',', $a[4][0]);
	}
	fclose($file);

	return $r;
}

system('mkdir -p web/plagiate');

require_once('render.php');

$polls =  getPrefixList('Fragment ');

$r = createLineNumberTable();

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

$i = 0;
foreach($fragments as $f) {
	$first = true;
	$a = processString($f['revisions'][0]['*']);
	if(isset($a[0]) && $a[0]) {
		if(!isset($r[(int) $a[1]])) {
			print "Keine Zeilenangaben fuer Seite ".$a[1]."!\n";
		} else {
			$fr[$i]['pagenumber'] = $a[1];
			$fr[$i]['lines'] = $a[2];
			$fr[$i]['startpos'] = calcStartpos($a[2], $r[(int) $a[1]]);
			$fr[$i]['length'] = calcLength($a[2], $r[(int) $a[1]]);
			$fr[$i]['orig'] = $a[6];
			$fr[$i]['category'] = $a[7];
			$fr[$i]['inLit'] = $a[8];
			$fr[$i]['src'] = $a[9];
			$fr[$i]['url'] = $a[10];
			$fr[$i]['anmerkung'] = $a[11];
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
			if($f['startpos'] + $f['length'] <= 910) {
				prepare_png($page, $i++, $f);
				$fragments[] = $f;
			} else {
				print 'Probleme mit Fragment '.$f['pagenumber'].' '.$f['lines']."!\n";
			}
		}

	$output = printout($fragments, $page);
	$file = fopen("web/$page.html", 'w'); 
	fwrite($file, $output);
	fclose($file);
}

