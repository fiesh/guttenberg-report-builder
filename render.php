<?php

function insert_plag($pn, $num, $f, $source)
{
    return '				<div id="plag'.$pn.'_'.$num.'" class="plag"><img src="plagiate/'.$pn.'_'.$num.'.png" /></div>'."\n";
}

function insert_orig($pn, $num, $f, $source)
{
    $tooltip =  'Quelle: '.preg_replace('/<\/?em>/', '', $source['renderedplain']);
    if (isset($f['note']) && !empty($f['note']))
        $tooltip .=  '    Anmerkung: '.str_replace('"','',$f['note']);

    $class = 'orig';
    if ($f['lines'] > 100)
        $class .= ' origfootnote';

    $orig = $f['orig'];
    return
    '				<div
              id="orig'.$pn.'_'.$num.'"
              class="'.$class.'"
              title="'.$tooltip.'"
              >'.$orig.'</div>'."\n";
}


function insert_script($pn, $num, $f, $source)
{
    $quelle = $source['rendered'];

    if($source['InLit'] === 'ja')
	    $lit = '<img src="accept.png" title="Quelle in Literaturverzeichnis vorhanden." />';
    else if($source['InFN'] === 'ja') //TODO
	    $lit = '<img src="infn.png" title="Quelle NUR in Fußnoten vorhanden." />';
    else
	    $lit = '<img src="error.png" title="Quelle NICHT in Literaturverzeichnis vorhanden!" />';

    if(isset($f['origlines']) && $f['origlines'])
        $foundat = 'Seite '.$f['origpage'].', Zeilen '.$f['origlines'];
    else
        $foundat = 'Seite '.$f['origpage'];

    if(isset($source['URL'])) {
        $xsource = '<div class="src"><a href="'.$source['URL'].'">'.$quelle.'</a> auf '.$foundat.'.</div><div class="inlit">'.$lit.'</div>';
    } else {
        $xsource = '<div class="src">'.$quelle.' auf '.$foundat.'.</div><div class="inlit">'.$lit.'</div>';
    }

    $fragtype = preg_replace('/^Kategorie:/', '', $f['category']);

    return '		$(\'#plag'.$pn.'_'.$num.'\').hover(
        function () {
        $(\'#infoblock-cat\').replaceWith(\'<div class="category" id="infoblock-cat"><a href="http://de.guttenplag.wikia.com/wiki/PlagiatsKategorien">'.$fragtype.'</a></div>\');
        $(\'#infoblock-src\').replaceWith(\'<div class="src" id="infoblock-src">'.$xsource.'</div>\');
            deselect(activeOrig);
            activeOrig = $(\'#orig'.$pn.'_'.$num.'\');
            select(activeOrig);
        },
        function () {
        }
    );';
}

function insert_css($pn, $num, $f)
{
    return '		#plag'.$pn.'_'.$num.' {
            z-index: 5;
            height: '.$f['length'].'px;
            position: absolute;
            top: '.($f['startpos']+8).'px;
        }
        #orig'.$pn.'_'.$num.' {
            z-index: 5;
            top: '.($f['startpos']-3).'px;
            min-height: '.$f['origlength'].'px;
            position: absolute;
        }'."\n";
}

function printout($fragments, $sources, $page)
{
    $ret ='<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="de">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title>Seite '.$page.' -- Interaktiver Guttenberg Report</title>
        <link rel="stylesheet" href="gr.css" />
        <script src="http://code.jquery.com/jquery-1.5.min.js"></script>
        <script type="text/javascript">

        function deselect(origDiv)
        {
            // reset z-level and bgcolor;
            if (origDiv) {
                origDiv.css("background", "white");
                origDiv.css("z-Index", "50");
            }
        }

        function select(origDiv)
        {
            // increase z-level and highlight with bgcolor;
            if (origDiv) {
                origDiv.css("background", "yellow");
                origDiv.css("z-Index", "500");
            }
        }

        </script>
    </head>
    <body>
        <div id="home"><a href="/"><img src="up.png" border="0"/></a></div>
        <div id="guttenberg-titel">Karl-Theodor zu Guttenberg, Verfassung und Verfassungsvertrag, 2009</div>
        <div id="wrapper">
            <div id="page">
';
    $i = 0;
    if(isset($fragments)) foreach($fragments as $f) {
        $ret .= insert_plag($page, $i++, $f, $sources[$f['src']]);
    }
    $ret .= '			</div>
        </div>
        <div id="infoblock">
          <div class="category" id="infoblock-cat"></div>
          <div class="src" id="infoblock-src"></div>';
    $i = 0;
    if(isset($fragments)) foreach($fragments as $f) {
        $ret .= insert_orig($page, $i++, $f, $sources[$f['src']]);
    }
    $ret .='
        </div>
        <div class="navigation">
';
    $ret .= '<div id="prev"><a href="'.($page > 2 ? sprintf('%03d', $page - 1).'.html' : '#').'"><img src="prev.png" border="0" /></a></div>';
    $ret .= '<div id="pagenum">'.$page.'</div>';
    $ret .= '<div id="next"><a href="'.($page < 474 ? sprintf('%03d', $page + 1).'.html' : '#').'"><img src="next.png" border="0" /></a></div>';
    $ret .= '
        </div>
    </body>
    <script type="text/javascript">
        var activeOrig = null;
';

    $i = 0;
    if(isset($fragments)) foreach($fragments as $f) {
        $ret .= insert_script($page, $i++, $f, $sources[$f['src']]);
    }

    $ret .= '
    </script>
    <style type="text/css">
';

    $ret .= '
        #page {
            width: 600px;
            height: 910px;
            padding-left: 2px;
            padding-right: 2px;
            border: 1px solid #000;
            background: url(images/'.$page.'_blur.jpg);
        }
';
    $i = 0;
    if(isset($fragments)) foreach($fragments as $f) {
        $ret .= insert_css($page, $i++, $f, $sources[$f['src']]);
    }

    $ret .= '	</style>
</html>
';
    return $ret;
}
