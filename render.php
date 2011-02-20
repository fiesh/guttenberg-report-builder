<?php

function insert_plag($pn, $num)
{
	return '				<div id="plag'.$pn.'_'.$num.'"><img src="plagiate/'.$pn.'_'.$num.'.png" /></div>'."\n";
}

function insert_script($pn, $num, $info)
{
	return '		$(\'#plag'.$pn.'_'.$num.'\').hover(
		function () {
			$(\'#infoblock\').replaceWith($(\'<div id="infoblock">'.$info.'</div>\'));
			//$(\'#plag'.$pn.'_'.$num.'\').replaceWith(\'<div id="plag'.$pn.'_'.$num.'_rb"><img src="plagiate/'.$pn.'_'.$num.'.png" /></div>\');
		}, 
		function () {
			//$(\'#plag'.$pn.'_'.$num.'_rb\').replaceWith(\'<div id="plag'.$pn.'_'.$num.'"><img src="plagiate/'.$pn.'_'.$num.'.png" /></div>\');
		}
	);';
}

function insert_css($pn, $num, $fragment)
{
	return '		#plag'.$pn.'_'.$num.' {
			z-index: 5;
			width: 600px;
			padding-left: 2px;
			padding-right: 2px;
			height: '.$fragment['length'].'px;
			position: absolute;
			top: '.$fragment['startpos'].'px;
		}
		#plag'.$pn.'_'.$num.'_rb {
			z-index: 10;
			width: 600px;
			padding: 0;
			height: '.($fragment['length']-4).'px;
			position: absolute;
			top: '.($fragment['startpos']-2).'px;
			border: 2px solid red;
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
		<title>Guttenberg Report</title>
		<link rel="stylesheet" href="gr.css" />
		<script src="http://code.jquery.com/jquery-1.5.min.js"></script>
	</head>
	<body>
		<div class="wrapper">
			<div id="leftpage">
';
	$i = 0;
	if(isset($fragments['left'])) foreach($fragments['left'] as $f) {
		$ret .= insert_plag($page, $i++);
	}
	$ret .= '			</div>
			<div id="rightpage">';

	$i = 0;
	if(isset($fragments['right'])) foreach($fragments['right'] as $f) {
		$ret .= insert_plag(sprintf('%03d', $page + 1), $i++);
	}
	$ret .= '			</div>
		</div>
		<div id="infoblock"></div>';
	$ret .= '<div class="navigation"><a href="'.($page > 2 ? sprintf('%03d', $page - 2).'.html' : '#').'">Eins zur&uuml;ck</a></div>';
	$ret .= '<div class="navigation"><a href="'.($page < 474 ? sprintf('%03d', $page + 2).'.html' : '#').'">Eins vor</a></div>';
	$ret .= '
	</body>
	<script type="text/javascript">
';

	$i = 0;
	if(isset($fragments['left'])) foreach($fragments['left'] as $f) {
		$info = prepare_expl($f);
		$ret .= insert_script($page, $i++, $info);
	}

	$i = 0;
	if(isset($fragments['right'])) foreach($fragments['right'] as $f) {
		$info = prepare_expl($f);
		$ret .= insert_script(sprintf('%03d', $page + 1), $i++, $info);
	}
	$ret .= '
	</script>
	<style type="text/css">
';

	$i = 0;
	if(isset($fragments['left'])) foreach($fragments['left'] as $f) {
		$ret .= insert_css($page, $i++, $f);
	}

	$i = 0;
	if(isset($fragments['right'])) foreach($fragments['right'] as $f) {
		$ret .= insert_css(sprintf('%03d', $page + 1), $i++, $f);
	}
	$ret .= '
		#leftpage {
			width: 600px;
			height: 910px;
			padding-left: 2px;
			padding-right: 2px;
			border: 1px solid #000;
			float: left;
			background: url(images/'.$page.'_blur.png);
		}
		#rightpage {
			width: 600px;
			padding-left: 2px;
			padding-right: 2px;
			height: 910px;
			border: 1px solid #000;
			float: right;
			background: url(images/'.(sprintf('%03d', $page + 1)).'_blur.png);
		}
	</style>
</html>
';
	return $ret;
}
