<?php

require "csvreader.php";	

function printout_index($plagsPerPage)
{
	$ret = '<?xml version="1.0" encoding="utf-8"?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
    "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>Interaktiver Guttenberg Report</title>
	<link href="styles.css" rel="stylesheet" type="text/css" />
	<meta http-equiv="Content-Type" content="text/html;charset=utf-8" />
</head>
<body>
<div id="wrapper">
	<h1>Interaktiver Guttenberg Report</h1>

	<h2>Eine graphische Aufbereitung der im GuttenPlag Wiki gesammelten Daten zu Karl-Theodor Freiherr zu Guttenbergs Dissertation „Verfassung und Verfassungsvertrag“</h2>
	<p>vielen Dank an fiesh, vis4net, pettar, monopteros, Adalbert, Egon, kahrl und viele andere Wikianer!</p>
		<p><small>Bei den folgenden Seiten handelt es sich um vollständig automatisch generierte Darstellungen der im <a href="http://de.guttenplag.wikia.com/wiki/GuttenPlag_Wiki">GuttenPlag Wiki</a> gefundenen Textstellen.<br />
Die farblich gekennzeichneten Seiten enthalten nicht ausgewiesene Zitate oder Plagiate aus anderen Veröffentlichungen. <br />
	Die dunkelroten Symbole kennzeichnen Seiten, auf denen Plagiate unterschiedlicher Quellen gefunden wurden.</small></p>

<noscript>
<p><small>(Die verlinkten Seiten nutzen JavaScript)</small></p>
</noscript>

<p><small>(Die farbliche Hervorhebung unten basiert auf den Daten der im Wiki gesammelten Seiten.  Diese sind noch nicht vollständig in das neue „Fragmente“-Format übertragen, so dass auf manchen rot hinterlegten Seiten die graphische Darstellung von Plagiaten fehlen kann.)</small></p>

<p><big>Der interaktive Guttenberg Report darf als ausgelagerter Teil des Wikis verstanden werden.  Sämtliche Inhalte dürfen daher unter Quellenangabe zu Pressezwecken verwendet werden.</big></p>
';

	$chapters = array(
		1 => array('Titel, Vorwort, Inhaltsverzeichnis', 1),
		15 => array('A. Einleitung', 1),
		19 => array('B. Verfassungserweckung und Verfassungsbestätigung', 1),
		20 => array('I. Eckpunkte der US-amerikanischen	Verfassungsentwicklung', 2),
		51 => array('II. Eckpunkte und Grundlagen der europäischen Verfassungsentwicklung sowie des Verfassungsverständnisses', 2),
		194 => array('III. Der Einfluss der amerikanischen Verfassung und des Verfassungsverständnisses auf europäische Rechtskultur(en), Rechtskulturzusammenhänge', 2),
		221 => array('IV. Die Bestätigung und Festigung des Verfassungsstaates (USA) bzw. der Verfassungsgemeinschaft (EU) durch Verfassunggebung, Verfassungsinterpretation und Verfassungsprinzipien', 2),
		358 => array('V. Zwei Verfassunggebungsprozesse: ein Resümee', 2),
		373 => array('C. Der Gottesbezug in den Verfassungen Europas und der USA / I. Einleitung', 1),
		374 => array('II.  Der Gottesbezug in den Verfassungen Europas', 2),
		391 => array('III. Gottesbezug und US-Verfassung; die Rechtsprechung des US-Supreme Court zur Trennung von Staat und Religion', 2),
		403 => array('Nachwort, Zusammenfassung', 1),
		408 => array('Anhänge, Literaturverzeichnis, Sachwortverzeichnis', 1),
	);

	$pageInfo = CSVUtil::read("pages.csv");

	$c = 0;
	for ($p=1; $p<=475; $p++) {
		if (isset($chapters[$p])) {
			if ($p > 1) {
				$ret .= '</div><br style="clear:both" /></div>';
				$ret .= "\n";
			}
			$ret .= '<div class="section'.($c%2 == 0 ? ' odd':'').' indent'.$chapters[$p][1].'">';
			$ret .= '<div class="desc">'.$chapters[$p][0].'</div><div class="pages">';
			$c++;
		}

		$words = trim($pageInfo[$p][4]);
		$sentences = trim($pageInfo[$p][3]);
		if ($p <= 4) $class = sprintf("%03d", $p);
		else if ($p <= 6) $class = "500";
		else if ($p <= 13) $class = "contents";
		else if ($p == 14) $class = "emptypage";
		else if ($p >= 408) $class = "appendix";
		else if ($words < 150) $class = "100";
		else if ($words < 250) $class = "200";
		else if ($words < 350) $class = "300";
		else if ($words < 450) $class = "400";
		else $class = "500";

		if(isset($plagsPerPage[$p])) {
			$plagCount = $plagsPerPage[$p];
		} else {
			$plagCount = 0;
		}
		if($plagCount >= 2) {
			$plagClass = 'page plag multi';
		} else if($plagCount >= 1) {
			$plagClass = 'page plag';
		} else {
			$plagClass = 'page';
		}

		$ret .= '<div class="'.$plagClass.'">';
		$ret .= '<a href="'.sprintf("%03d",$p).'.html">';
		$ret .= '<img src="'.$class.'.png" alt="S. '.$p.', Sätze: '.$sentences.',  Wörter: '.$words.'" title="Seite '.$p.'" />';
		$ret .= '</a></div>';
	}
	
	$ret .= '</div><br style="clear:both" /></div>
<div class="footer">
Zuletzt aktualisiert am '.strftime('%c', time()).'<br />
<a href="impressum.html">Impressum</a>
</div>

</div>
</body>
</html>
';
	return $ret;
}
