<?php

function insert_plag($pn, $num)
{
    return '				<div id="plag'.$pn.'_'.$num.'" class="plag"><img src="plagiate/'.$pn.'_'.$num.'.png" /></div>'."\n";
}

function insert_orig($pn, $f, $num)
{
    $tooltip =  'Quelle: '.str_replace('"','',$f['src']);
    if (isset($f['anmerkung']) && !empty($f['anmerkung']))
        {
            $tooltip .=  '    Anmerkung: '.str_replace('"','',$f['anmerkung']);
        }

    $orig = $f['orig'];
    return
    '				<div
              id="orig'.$pn.'_'.$num.'"
              class="orig"
              title="'.$tooltip.'"
              >'.$orig.'</div>'."\n";
}


function insert_script($pn, $num, $f)
{
    $quelle =  str_replace('"','',$f['src']);
    $source = '';
    if($f['inLit'] === 'ja')
	    $lit = '<img src="accept.png" title="Quelle in Literaturverzeichnis vorhanden." />';
    else
	    $lit = '<img src="error.png" title="Quelle NICHT in Literaturverzeichnis vorhanden!" />';
    if(isset($f['url'])) {
        $source .= '<div class="src"><a href="'.$f['url'].'">'.$quelle.'</a> auf Seite '.$f['seitefund'].', Zeilen '.$f['zeilenfund'].'.</div><div class="inlit">'.$lit.'</div>';
    } else {
        $source .= '<div class="src">'.$quelle.' auf Seite '.$f['seitefund'].', Zeilen '.$f['zeilenfund'].'.</div><div class="inlit">'.$lit.'</div>';
    }

    return '		$(\'#plag'.$pn.'_'.$num.'\').hover(
        function () {
        $(\'#infoblock-cat\').replaceWith(\'<div class="category" id="infoblock-cat"><a href="http://de.guttenplag.wikia.com/wiki/PlagiatsKategorien">'.$f['category'].'</a></div>\');
        $(\'#infoblock-src\').replaceWith(\'<div class="src" id="infoblock-src">'.$source.'</div>\');
            deselect(activeOrig);
            activeOrig = $(\'#orig'.$pn.'_'.$num.'\');
            select(activeOrig);
        },
        function () {
        }
    );';
}

function insert_css($pn, $num, $fragment)
{
    return '		#plag'.$pn.'_'.$num.' {
            z-index: 5;
            height: '.$fragment['length'].'px;
            position: absolute;
            top: '.$fragment['startpos'].'px;
        }
        #orig'.$pn.'_'.$num.' {
            z-index: 5;
            top: '.($fragment['startpos']-5).'px;
            min-height: '.$fragment['length'].'px;
            position: absolute;
        }'."\n";
}

function prepare_expl($f)
{
    $info = '<div class="category">'.$f['category'].'</div>';
    if(isset($f['url'])) {
        $info .= '<div class="src"><a href="'.$f['url'].'">'.$f['src'].'</a></div>';
    } else {
        $info .= '<div class="src">'.$f['src'].'</div>';
    }
    $info .= '<div class="orig">'.$f['orig'].'</div>';
    return $info;
}

function printout($fragments, $page)
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
        <div class="home"><a href="/"><img src="up.png" border="0"/></a></div>
        <div class="guttenberg-titel">Karl-Theodor zu Guttenberg, Verfassung und Verfassungsvertrag, 2009</div>
        <div class="wrapper">
            <div id="page">
';
    $i = 0;
    if(isset($fragments)) foreach($fragments as $f) {
        $ret .= insert_plag($page, $i++);
    }
    $ret .= '			</div>
        </div>
        <div id="infoblock">
          <div class="category" id="infoblock-cat"></div>
          <div class="src" id="infoblock-src"></div>';
    $i = 0;
    if(isset($fragments)) foreach($fragments as $f) {
        $ret .= insert_orig($page, $f, $i++);
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
        $ret .= insert_script($page, $i++, $f);
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
        $ret .= insert_css($page, $i++, $f);
    }

    $ret .= '	</style>
</html>
';
    return $ret;
}
