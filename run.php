<?php

function calcStartpos($z)
{
	preg_match_all('/(\d+)-(\d+)/', $z, $a);
	return -20 + (int) (95 + round(16.6*$a[1][0]));
}

function calcLength($z)
{
	preg_match_all('/(\d+)-(\d+)/', $z, $a);
	if(!$a[2][0])
		$a[2][0] = $a[1][0] + 1;
	return 40 + (int) (round(16.6 * ($a[2][0] - $a[1][0])));
}

function prepare_png($pn, $num, $f)
{
	$cmd = 'convert images/'.$pn.'.png -crop 600x'.$f['length'].'+0+'.$f['startpos'].' web/plagiate/'.$pn.'_'.$num.'.png';

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
	return unserialize(file_get_contents('http://de.guttenplag.wikia.com/api.php?action=query&prop=revisions&rvprop=content&format=php&generator=allpages&gaplimit=5000&gapprefix='.urlencode($prefix)));
}

system('mkdir -p web/plagiate');

require_once('render.php');

$fragments =  getPrefixList('Fragment ');

$i = 0;
foreach($fragments['query'] as $frag) {
	foreach($frag as $f) {
		$first = true;
		$a = processString($f['revisions'][0]['*']);
		if(isset($a[0]) && $a[0]) {
			$fr[$i]['pagenumber'] = $a[1];
			$fr[$i]['lines'] = $a[2];
			$fr[$i]['startpos'] = calcStartpos($a[2]);
			$fr[$i]['length'] = calcLength($a[2]);
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

for($page = 2; $page <= 474; $page += 2) {
//for($page = 112; $page <= 112; $page += 2) {
	$fragments = array();
	$page = sprintf('%03d', $page);
	$i = 0;
	foreach($fr as $f)
		if($f['pagenumber'] == $page) {
			if($f['startpos'] + $f['length'] <= 910) {
				prepare_png($page, $i++, $f);
				$fragments['left'][] = $f;
			} else {
				print 'Probleme mit Fragment '.$f['pagenumber'].' '.$f['lines']."!\n";
			}
		}
	$i = 0;
	foreach($fr as $f)
		if($f['pagenumber'] == $page + 1) {
			if($f['startpos'] + $f['length'] <= 910) {
				prepare_png(sprintf('%03d', $page + 1), $i++, $f);
				$fragments['right'][] = $f;
			} else {
				print 'Probleme mit Fragment '.$f['pagenumber'].' '.$f['lines']."!\n";
			}
		}

	$output = printout($fragments, $page);
	$file = fopen("web/$page.html", 'w'); 
	fwrite($file, $output);
	fclose($file);
}

