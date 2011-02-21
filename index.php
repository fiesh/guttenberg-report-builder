<?php print '<?xml version="1.0" encoding="utf-8"?>' ?>
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
	<p>vielen Dank an vis4net, pettar, monopteros, Adalbert, Egon und viele andere Wikianer!</p>
		<p><small>Bei den folgenden Seiten handelt es sich um vollständig automatisch generierte Darstellungen der im <a href="http://de.guttenplag.wikia.com/wiki/GuttenPlag_Wiki">Guttenplag Wiki</a> gefundenen Textstellen.<br />
Die farblich gekennzeichneten Seiten enthalten nicht ausgewiesene Zitate oder Plagiate aus anderen Veröffentlichungen. <br />
	Die dunkelroten Symbole kennzeichnen Seiten, auf denen Plagiate unterschiedlicher Quellen gefunden wurden.</small></p>

<?php

$plag_pages = explode(",", "15,16,17,25,27,30,32,33,34,35,37,38,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,65,66,67,68,69,70,72,73,74,75,77,78,79,80,81,82,84,85,86,87,88,90,92,93,94,95,96,99,100,101,102,103,104,105,106,107,108,109,110,111,112,113,114,115,116,117,118,119,120,121,122,123,124,125,126,127,128,129,130,131,132,133,134,135,136,139,142,143,144,145,146,147,148,149,150,151,153,154,155,156,157,158,159,160,164,166,167,169,170,171,172,173,174,175,178,179,180,181,182,183,184,185,186,187,188,189,190,191,192,193,195,196,197,198,213,215,216,217,218,219,223,224,226,227,228,229,235,236,237,238,239,241,242,243,244,249,250,253,256,257,258,259,260,261,263,264,266,271,272,273,274,276,280,281,282,283,285,286,288,289,294,299,303,304,306,307,308,309,310,311,312,313,315,316,317,318,319,320,321,322,323,324,325,326,327,328,329,330,331,333,334,335,336,337,338,339,340,341,342,343,344,345,346,347,348,349,350,351,352,353,354,355,356,357,358,359,363,364,365,366,367,368,369,370,371,372,378,379,381,383,391,393,394,397,398,402,403,405");

$multi_plags = array(16,45,47,53,55,59,67,102,120,122,125,128,134,143,144,172,181,187,189,197,217,218,224,260,285,286,303,311,327,337,344,356,369,405);

require "csvreader.php";	

$chapters = array(
	1 => array('Titel, Vorwort, Inhaltsverzeichnis', 1),
	15 => array('A. Einleitung', 1),
	19 => array('B. Verfassungserweckung und Verfassungsbestätigung', 1),
	20 => array('I. Eckpunkte der US-amerikanischen	Verfassungsentwicklung', 2),
	51 => array('II. Eckpunkte und Grundlagen der europäischen Verfassungsentwicklung sowie des Verfassungsverstänisses', 2),
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
		if ($p > 1) print '</div><br style="clear:both" /></div>';
		
		print '<div class="section'.($c%2 == 0 ? ' odd':'').' indent'.$chapters[$p][1].'">';
		print '<div class="desc">'.$chapters[$p][0].'</div><div class="pages">';
		$c++;
	}
	
	$words = $pageInfo[$p][4];
	$sentences = $pageInfo[$p][3];
	if ($p >= 408) $class = "appendix";
	else if ($p < 6) $class = "title";
	else if ($sentences > 100) $class = "index";
	else if ($words < 100) $class = "100";
	else if ($words < 300) $class = "200";
	else if ($words < 400) $class = "300";
	else if ($words < 400) $class = "400";
	else $class = "500";
	
	if (in_array($p, $plag_pages)) {
		print '<div class="page plag'.(in_array($p, $multi_plags) ? " multi":"").'">';
		print '<a href="'.sprintf("%03d",$p).'.html"><img src="'.$class.'.png" alt="S. '.$p.', Sätze: '.$sentences.',  Wörter: '.$words.'" title="Seite '.$p.'" /></a></div>';
	} else {
		print '<div class="page">';
		print '<a href="'.sprintf("%03d",$p).'.html"><img src="'.$class.'.png" alt="S. '.$p.', Sätze: '.$sentences.',  Wörter: '.$words.'" title="Seite '.$p.'" /></a></div>';
	}
	
	
	//if ($p > 1 && $p%10 == 0) print '</div>';
}

?></div><br style="clear:both" /></div>
<div class="footer">
Zuletzt aktualisier am <?php echo strftime('%c', time()); ?>
</div>

</div>
</body>
</html>
