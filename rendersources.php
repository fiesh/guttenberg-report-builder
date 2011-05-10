<?php

// This code is loosely based on dinat.bst (more accurately,
// dinat-custom.bst from gut-ab).


class SourceRenderer 
{
	private $out;
	private $buffer;
	private $state;
	private $wikiLinkDone;
	private $plain;

	// surrounds text with <em></em> unless in plain mode
	private function emphasize($s) {
		if ($this->plain)
			return $s;
		else
			return '<em>'.$s.'</em>';
	}

	// this function tries to emulate bibtex's single-name parsing
	// note: only the "First von Last" form is supported at this moment
	private function renderName($name)
	{
		// split string into tokens
		$depth = 0;
		$tokens = array();
		$token = '';
		foreach(str_split($name) as $c) {
			if($c == '{') {
				++$depth;
			} else if($depth > 0 && $c == '}') {
				--$depth;
			} else if($depth == 0 && $c == ' ') {
				if($token)
					$tokens[] = trim($token);
				$token = '';
			} else {
				$token .= $c;
			}
		}
		if($token)
			$tokens[] = trim($token);

		// split token list into first/von/last parts
		$firstTokens = array();
		$vonTokens = array();
		$lastTokens = array();
		foreach($tokens as $token) {
			if(!empty($lastTokens)) {
				$lastTokens[] = $token;
			} else if(ctype_lower(mb_substr($token, 0, 1))) {
				// first character is in lower case:
				// assume it's a 'von' token
				$vonTokens[] = $token;
			} else if(!empty($vonTokens)) {
				$lastTokens[] = $token;
			} else {
				$firstTokens[] = $token;
			}
		}
		if(empty($lastTokens)) {
			if($vonTokens) {
				$lastTokens = $vonTokens;
				$vonTokens = array();
			} else if($firstTokens) {
				$lastTokens[] = array_pop($firstTokens);
			}
		}

		// re-combine all parts
		$s = '';
		if($lastTokens) {
			$s .= implode(' ', $lastTokens);
		}
		foreach($firstTokens as $i => $token) {
			if($i == 0) {
				if($s) $s .= ', ';
				$s .= $token;
			} else {
				$s .= ' ' . $token[0] . '.';
			}
		}
		if($vonTokens) {
			if($s) $s .= ' ';
			$s .= implode(' ', $vonTokens);
		}
		return $s;
	}

	// this function tries to emulate bibtex's multi-name parsing
	// (with commas being separators instead of 'and')
	private function renderNames($names,
		$isInvolvedEntry, $defaultOccupation)
	{
		$s = '';
		$depth = 0;
		$startpos = 0;
		$len = strlen($names);
		for($i = 0; $i <= $len; ++$i) {  // kein off-by-one
			$c = ($i < $len) ? @$names[$i] : false;
			if($i == $len || ($c == ',' && $depth <= 0)) {
				$part = trim(substr($names, $startpos, $i-$startpos));
				if($isInvolvedEntry &&
				  (preg_match('/^(.*)\[([^(]*)\]$/', $part, $match) ||
				   preg_match('/^(.*)\(([^(]*)\)$/', $part, $match))) {
					$name = $this->renderName(trim($match[1]));
					$occupation = trim($match[2]);
				} else if(!empty($part)) {
					$name = $this->renderName(trim($part));
					$occupation = $defaultOccupation;
				}

				if($s) $s .= '&nbsp;; ';
				$s .= $name;
				if($occupation) $s .= ' ('.$occupation.')';

				$startpos = $i+1;
			} else if($c == '(' || $c == '[' || $c == '{') {
				++$depth;
			} else if($c == ')' || $c == ']' || $c == '}') {
				--$depth;
			}
		}
		return $s;
	}

	private function renderAuthors($source)
	{
		if(isset($source['Autor'])) {
			return $this->renderNames($source['Autor'], false, '');
		} else {
			return '';
		}
	}

	private function renderEditorsInvolved($source)
	{
		if(isset($source['Hrsg'])) {
			$editors = $this->renderNames($source['Hrsg'], false, 'Hrsg.');
		} else {
			$editors = '';
		}
		if(isset($source['Beteiligte'])) {
			$involved = $this->renderNames($source['Beteiligte'], true, '');
		} else {
			$involved = '';
		}
		if($editors && $involved)
			$editors .= '&nbsp;; ';
		return $editors . $involved;
	}

	private function renderAuthorsEditorsInvolved($source)
	{
		$authors = $this->renderAuthors($source);
		$editorsInvolved = $this->renderEditorsInvolved($source);
		if($authors && $editorsInvolved)
			$authors .= '&nbsp;; ';
		return $authors . $editorsInvolved;
	}

	private function renderTitle($source)
	{
		if(isset($source['Titel'])) {
			return $source['Titel'];
		} else {
			return '';
		}
	}

	private function renderBtitle($source)
	{
		if(isset($source['Titel'])) {
			return $this->emphasize($source['Titel']);
		} else {
			return '';
		}
	}

	private function renderBtitleVolume($source)
	{
		$s = '';
		if(isset($source['Nummer'])) {
			$s .= $this->renderBtitle($source);
		} else {
			if(isset($source['Reihe'])) {
				if(isset($source['Jahrgang'])) {
					$s .= $this->emphasize($source['Reihe']) . '. ';
					$s .= 'Bd. ' . $source['Jahrgang'] . ': ';
				}
				$s .= $this->renderBtitle($source);
			} else {
				$s .= $this->renderBtitle($source);
				if(isset($source['Jahrgang'])) {
					$s .= '. Bd. ' . $source['Jahrgang'];
				}
			}
		}
		return $s;
	}

	private function renderInEditorsBooktitle($source)
	{
		$s = '';
		if(isset($source['Sammlung'])) {
			$s .= 'In: ';
			$editorsInvolved = $this->renderEditorsInvolved($source);
			if($editorsInvolved)
				$s .= $editorsInvolved . ': ';
			$s .= $this->emphasize($source['Sammlung']);
			if(isset($source['Jahrgang'])) {
				$s .= ' Bd.&nbsp;' . $source['Jahrgang'];
			}
		}
		return $s;
	}

	private function renderEdition($source)
	{
		if(isset($source['Ausgabe'])) {
			return $source['Ausgabe'];
		} else {
			return '';
		}
	}

	private function renderUrl($source)
	{
		if(isset($source['URL'])) {
			$urlfield = $this->korrUrlForBibliography($source['URL']);
			$urlparts = explode(' ', $urlfield, 2);
			if ($this->plain) {
				$result = $urlparts[0];
			} else {
				$result = '<a href="'.trim($urlparts[0]).'">URL</a>';
			}
			if(isset($urlparts[1])) {
				$result .= ' ' . trim($urlparts[1]);
			}
			return $result;
		} else {
			return '';
		}
	}

	private function renderDate($source)
	{
		$s = '';
		if(isset($source['Tag'])) {
			$s .= $source['Tag'];
		}
		if(isset($source['Monat'])) {
			if($s) $s .= '. ';
			$s .= $source['Monat'];
		}
		if(isset($source['Jahr'])) {
			if($s) $s .= ' ';
			$s .= $source['Jahr'];
		}
		return $s;
	}

	private function renderVolumeYearNumberPages($source)
	{
		$s = '';
		if(isset($source['Jahrgang'])) {
			$s .= $source['Jahrgang'];
		}
		if(isset($source['Jahr'])) {
			if($s) $s .= ' ';
			$s .= '(' . $source['Jahr'] . ')';
		}
		if(isset($source['Tag']) || isset($source['Monat'])) {
			if($s) $s .= ', ';
		}
		if(isset($source['Tag'])) {
			$s .= $source['Tag'] . '. ';
		}
		if(isset($source['Monat'])) {
			$s .= $source['Monat'];
		}
		if(isset($source['Nummer'])) {
			if($s) $s .= ', ';
			$s .= 'Nr.&nbsp;' . $source['Nummer'];
		}
		if(isset($source['Seiten']) && $s) {
			$s .= ', ';
			if(isset($source['Titel']))
				$s .= 'S.&nbsp;' . $source['Seiten'];
			else
				$s .= $source['Seiten'] . 'S';
		}
		return $s;
	}

	private function renderAddressPublisherYear($source)
	{
		$s = '';
		if(isset($source['Ort'])) {
			$s .= $source['Ort'];
		}
		if(isset($source['Verlag'])) {
			if($s) $s .= '&nbsp;: ';
			$s .= $source['Verlag'];
		}
		if(!$s && isset($source['URL'])) {
			$s .= $this->renderUrl($source);
		}
		$date = $this->renderDate($source);
		if($date) {
			if($s) $s .= ', ';
			$s .= $date;
		}
		return $s;
	}

	private function renderSeriesNumber($source)
	{
		if(isset($source['Jahrgang'])) {
			return '';
		} else if(isset($source['Reihe']) || isset($source['Nummer'])) {
			$s = '(';
			if(isset($source['Reihe']))
				$s .= $source['Reihe'];
			if(isset($source['Reihe']) && isset($source['Nummer']))
				$s .= ' ';
			if(isset($source['Nummer']))
				$s .= $source['Nummer'];
			$s .= ')';
			return $s;
		} else {
			return '';
		}
	}

	// Hack fuer [http:// Linktext]-Links im URL-Feld (von gut-ab)
	// wird wegen umbruch von zu breiten floats im Wiki (IE/Chrome) benoetigt
	private function korrUrlForBibliography($s)
	{
		$prots = 'http|https|ftp';
		$schemeRegex = '(?:(?:'.$prots.'):\/\/)';
		return preg_replace('/^\[('.$schemeRegex.'[^][{}<>"\\x00-\x20\\x7F]+) *([^\]\\x00-\\x08\\x0A-\\x1F]*)?\]$/s', '$1', $s);
	}

	private function bibitemStart($plain)
	{
		$this->out = '';
		$this->buffer = '';
		$this->state = 'before.all';
		$this->wikiLinkDone = false;
		$this->plain = $plain;
	}

	private function bibitemEnd()
	{
		$this->out .= $this->buffer;
		$this->buffer = '';
		$this->state = 'after.all';
		$this->wikiLinkDone = true;
	}

	private function outputBlock($s)
	{
		if($this->state == 'after.block') {
			if(!preg_match('/[.!?]$/', $this->buffer))
				$this->buffer .= '.';
			$this->out .= $this->buffer . ' ';
		} else if($this->state == 'before.all') {
			$this->out .= $this->buffer;
		} else if($this->state == 'colon.after') {
			$this->out .= $this->buffer . ': ';
		} else if($this->state == 'period.dash') {
			$this->out .= $this->buffer . '. - ';
		} else if($this->state == 'mid.sentence') {
			$this->out .= $this->buffer . ', ';
		} else {
			$this->out .= $this->buffer . ' ';
		}
		$this->buffer = $s;
		$this->state = 'after.block';
	}

	private function output($s)
	{
		if(trim($s) != '') {
			$this->outputBlock($s);
		}
	}

	private function outputWikiLink($source)
	{
		if($this->buffer && !$this->wikiLinkDone && !$this->plain) {
			$this->buffer =
				'<a href="http://de.guttenplag.wikia.com/wiki/'
				. str_replace(' ', '_', $source['title'])
				. '">'
				. $this->buffer
				. '</a>';
			$this->wikiLinkDone = true;
		}
	}

	private function setStateColonAfter()
	{
		if($this->state != 'before.all')
			$this->state = 'colon.after';
	}

	private function setStateMidSentence()
	{
		if($this->state != 'before.all')
			$this->state = 'mid.sentence';
	}

	private function setStateAfterSentence()
	{
		if($this->state != 'before.all')
			$this->state = 'after.sentence';
	}

	private function setStatePeriodDash()
	{
		if($this->state != 'before.all')
			$this->state = 'period.dash';
	}

	private function article($source, $plain)
	{
		$this->bibitemStart($plain);
		$this->output($this->renderAuthors($source));
		$this->outputWikiLink($source);
		$this->setStateColonAfter();
		$this->output($this->renderTitle($source));
		$this->outputWikiLink($source);
		if($this->state == 'before.all') {
			$this->output($this->emphasize($source['Zeitschrift']));
		} else {
			$this->output('In: ' . $this->emphasize($source['Zeitschrift']));
		}
		$this->setStateAfterSentence();
		$this->output($this->renderVolumeYearNumberPages($source));
		if(isset($source['URL'])) {
			$this->setStatePeriodDash();
			$this->output($this->renderUrl($source));
		}
		if(isset($source['ISSN'])) {
			$this->setStatePeriodDash();
			$this->output('ISSN ' . $source['ISSN']);
		}
		$this->bibitemEnd();
	}

	private function book($source, $plain)
	{
		$this->bibitemStart($plain);
		$this->output($this->renderAuthorsEditorsInvolved($source));
		$this->outputWikiLink($source);
		$this->setStateColonAfter();
		$this->output($this->renderBtitleVolume($source));
		$this->outputWikiLink($source);
		$this->output($this->renderEdition($source));
		$this->output($this->renderAddressPublisherYear($source));
		$this->setStateAfterSentence();
		$this->output($this->renderSeriesNumber($source));
		if(isset($source['Seiten'])) {
			$this->setStatePeriodDash();
			$this->output($source['Seiten'] . 'S');
		}
		if(isset($source['Ort']) || isset($source['Verlag'])) {
			if (isset($source['URL'])) {
				$this->setStatePeriodDash();
				$this->output($this->renderUrl($source));
			}
		}
		if(isset($source['ISBN'])) {
			$this->setStatePeriodDash();
			$this->output('ISBN ' . $source['ISBN']);
		}
		$this->bibitemEnd();
	}

	private function incollection($source, $plain)
	{
		$this->bibitemStart($plain);
		$this->output($this->renderAuthors($source));
		$this->outputWikiLink($source);
		$this->setStateColonAfter();
		$this->output($this->renderTitle($source));
		$this->outputWikiLink($source);
		$this->output($this->renderInEditorsBooktitle($source));
		$this->output($this->renderEdition($source));
		$this->output($this->renderAddressPublisherYear($source));
		$this->setStateAfterSentence();
		$this->output($this->renderSeriesNumber($source));
		$this->setStateMidSentence();
		if(isset($source['Seiten'])) {
			$this->output('S.&nbsp;' . $source['Seiten']);
		}
		if(isset($source['Ort']) || isset($source['Verlag'])) {
			if (isset($source['URL'])) {
				$this->setStatePeriodDash();
				$this->output($this->renderUrl($source));
			}
		}
		if(isset($source['ISBN'])) {
			$this->setStatePeriodDash();
			$this->output('ISBN ' . $source['ISBN']);
		} else if(isset($source['ISSN'])) {
			$this->setStatePeriodDash();
			$this->output('ISSN ' . $source['ISSN']);
		}
		$this->bibitemEnd();
	}

	private function misc($source, $plain)
	{
		$this->bibitemStart($plain);
		$this->outputBlock(preg_replace('/^Kategorie:/', '', $source['title']));
		$this->outputWikiLink($source);
		$this->bibitemEnd();
	}

	public static function run($source, $plain)
	{
		// remove empty values
		foreach(array_keys($source) as $key) {
			if(trim($source[$key]) == '')
				unset($source[$key]);
		}

		// remove square brackets surrounding whole author/editor field
		foreach(array('Autor', 'Hrsg') as $key) {
			if(isset($source[$key]))
				$source[$key] = preg_replace('/^\s*\[(.*)\]\s*$/', '$1', $source[$key]);
		}

		$renderer = new SourceRenderer();

		if(isset($source['Zeitschrift'])) {
			$renderer->article($source, $plain);
		} else if(isset($source['Sammlung'])) {
			$renderer->incollection($source, $plain);
		} else if(isset($source['Verlag'])) {
			$renderer->book($source, $plain);
		} else {
			$renderer->misc($source, $plain);
		}
		return $renderer->out;
	}
}

function renderSource($source, $plain)
{
	return SourceRenderer::run($source, $plain);
}
