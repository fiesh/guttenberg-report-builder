<?php

define('ABSATZ_LAENGE', 10);
define('ZEILEN_LAENGE', 16.6);
define('ZUSATZ', 0);
define('ZEILEN_MARGIN_OBEN', 95);
define('FUSSNOTEN_MARGIN_UNTEN', 75);
define('FUSSNOTEN_LAENGE', 14.4);
define('FUSSNOTEN_ABSATZ_LAENGE', 3.5);
define('HOEHE', 910);

$whitelist = array('KomplettPlagiat', 'Verschleierung', 'HalbsatzFlickerei', 'ShakeAndPaste', 'ÜbersetzungsPlagiat', 'StrukturPlagiat', 'BauernOpfer', 'VerschärftesBauernOpfer');

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

	return -ZUSATZ + $startpos + (int) round(FUSSNOTEN_LAENGE * ($zeile-1) + $pos);
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

function calcStartpos($z, $zeilen, $manualPosition)
{
	preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $z, $a);
	if(!$a[2])
		$a[2] = $a[1];
	$firstline = (int) $a[1];
	$lastline = (int) $a[2];

	if(isset($manualPosition[$firstline][$lastline]['start']))
		return HOEHE * $manualPosition[$firstline][$lastline]['start'];
	else if($firstline > 100) // Fussnote
		return calcStartposFussnote($firstline - 100, $zeilen['fussnoten']);
	else
		return calcStartposZeile($firstline, $zeilen['zeilen']);
}

function calcLength($z, $zeilen, $manualPosition)
{
	preg_match('/^\s*(\d+)\s*-\s*(\d+)\s*$/', $z, $a);
	if(!$a[2])
		$a[2] = $a[1];
	$firstline = (int) $a[1];
	$lastline = (int) $a[2];

	if(isset($manualPosition[$firstline][$lastline]['length']))
		return HOEHE * $manualPosition[$firstline][$lastline]['length'];
	else if($firstline > 100) // Fussnote
		return calcLengthFussnote($firstline - 100, $lastline - 100, $zeilen['fussnoten']);
	else
		return calcLengthZeile($firstline, $lastline, $zeilen['zeilen']);
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

function getWikitextPayloadLines($pagetitle)
{
	$wikitext = explode("\n", file_get_contents('http://de.guttenplag.wikia.com/index.php?action=raw&templates=expand&title='.urlencode($pagetitle)));
	$result = array();
	foreach($wikitext as $line) {
		# remove comment lines
		# (== headers ==, # comments, <pre>, </pre>, empty lines)
		if(!(preg_match('/^\s*(==.*==|#.*|<\/?pre>)?\s*$/', $line))) {
			$result[] = $line;
		}
	}
	return $result;
}

function createLineNumberTable()
{
	foreach(getWikitextPayloadLines("Zeilenanzahl/Rohdaten") as $line) {
		preg_match('/^\s*(\d+):(\d+):([\d,]*):([\d,]*)\s*$/', $line, $a);
		$r[(int) $a[1]]['zeilen'] = explode(',', $a[3]);
		$r[(int) $a[1]]['fussnoten'] = explode(',', $a[4]);
	}
	return $r;
}

function createManualPositionTable()
{
	foreach(getWikitextPayloadLines("Visualisierungen/ReportBuilderManualPosition") as $line) {
		preg_match('/^\s*Fragment[ _](\d+)[ _](\d+)-(\d+)\s+(\d+(\.\d+)?)%?\s+(\d+(\.\d+)?)%?\s*$/i', $line, $a);
		$pagenum = (int) $a[1];
		$firstline = (int) $a[2];
		$lastline = (int) $a[3];
		$man[$pagenum][$firstline][$lastline]['start'] = (double) $a[4] / 100.0;
		$man[$pagenum][$firstline][$lastline]['length'] = (double) $a[6] / 100.0;
	}
	return $man;
}

system('mkdir -p web/plagiate');

require_once('render.php');

$polls =  getPrefixList('Fragment ');

$r = createLineNumberTable();
$man = createManualPositionTable();

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
if(isset($entries['query']['pages']))
	$fragments = array_merge($fragments, $entries['query']['pages']);

$i = 0;
foreach($fragments as $f) {
	$first = true;
	$a = processString($f['revisions'][0]['*']);
	if(isset($a[0]) && $a[0]) {
		$pagenum = (int) $a[1];
		if(!isset($r[$pagenum])) {
			print "Keine Zeilenangaben fuer Seite ".$a[1]."!\n";
		} else if(in_array($a[7], $whitelist)) {
			$fr[$i]['pagenumber'] = $a[1];
			$fr[$i]['lines'] = $a[2];
			$fr[$i]['startpos'] = calcStartpos($a[2], $r[$pagenum], $man[$pagenum]);
			$fr[$i]['length'] = calcLength($a[2], $r[$pagenum], $man[$pagenum]);
			$fr[$i]['orig'] = $a[6];
			$fr[$i]['category'] = $a[7];
			$fr[$i]['inLit'] = $a[8];
			$fr[$i]['src'] = $a[9];
			$fr[$i]['url'] = $a[10];
			$fr[$i]['anmerkung'] = $a[11];
			$fr[$i]['seitefund'] = $a[4];
			$fr[$i]['zeilenfund'] = $a[5];
			$i++;
		} else {
			print "Ignoriere: $a[1] $a[2]\n";
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
			if($f['startpos'] + $f['length'] <= HOEHE) {
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

