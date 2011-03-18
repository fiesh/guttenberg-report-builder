<?php

require_once('WikiLoader.php');

class FragmentLoader {
	static private function processString($s)
	{
		$needle = '';
		for($i = 1; $i < 12; $i++)
			$needle .= 'val_'.$i.'="([^"]*)"\s+';
		if (preg_match_all("/$needle/", $s, $match) === 1) {
			for($i = 1; $i < 12; $i++) {
				$a[$i] = trim($match[$i][0]);
			}
			return $a;
		} else {
			return false;
		}
	}

	static private function collectCategories($entry)
	{
		$cats = array();
		if(isset($entry['categories']))
			foreach($entry['categories'] as $c)
				$cats[] = $c['title'];
		$cats = array_unique($cats);
		sort($cats);
		return $cats;
	}

	static private function processFrags($entries, &$ignored = array())
	{
		$titleBlacklist = array('Fragment 99999 11-22');
		$fragments = array();
		foreach($entries as $e) {
			$a = self::processString($e['revisions'][0]['*']);
			if($a !== false && $a[1] && !in_array($e['title'], $titleBlacklist)) {
				$a['wikiTitle'] = $e['title'];
				$a['categories'] = self::collectCategories($e);
				$fragments[] = $a;
			} else {
				$ignored[] = $e['title'];
			}
		}
		return $fragments;
	}

	static public function getFragments(&$ignored = array())
	{
		$entries = WikiLoader::getEntriesWithPrefix('Fragment', true, true);
		return self::processFrags($entries, $ignored);
	}

	static public function getFragmentsG2006(&$ignored = array())
	{
		$entries = WikiLoader::getEntriesWithPrefix('Guttenberg-2006/', true, true);
		$fragmentTitles = array();
		foreach($entries as $e) {
			if(preg_match('/^Guttenberg-2006\/(\d{3})$/', $e['title'], $match) && $match[1] >= 1) {
				$pagenum = (int) $match[1];
				$raw = $e['revisions'][0]['*'];
				$raw = preg_replace('/<!--.*?-->/s', '', $raw);
				preg_match_all('/\{\{:([- _A-Za-z0-9\/]*?)\}\}/', $raw, $match);
				$i = 0;
				while(isset($match[1][$i])) {
					$fragmentTitles[] = str_replace('_', ' ', $match[1][$i]);
					++$i;
				}
			}
		}

		$fragmentEntries = WikiLoader::getEntriesByTitles($fragmentTitles);
		return self::processFrags($fragmentEntries, $ignored);
	}

	static private function parseFragmentType($rawText)
	{
		$fragtype = array();
		if(preg_match('/<!--\s*prioritaet\s*=\s*(-?\s*\d+)/si', $rawText, $match)) {
			$fragtype['priority'] = (int) preg_replace('/\s/', '', $match[1]);
		} else {
			$fragtype['priority'] = 0;
		}
		return $fragtype;
	}

	static public function getFragmentTypes(&$ignored = array())
	{
		$pageids = WikiLoader::getCategoryMembers('Kategorie:PlagiatsKategorien');
		$entries = WikiLoader::getEntries($pageids, true, true);

		$fragtypes = array();
		foreach($entries as $entry) {
			if (substr($entry['title'], 0, 10) == 'Kategorie:') {
				$fragtype = self::parseFragmentType($entry['revisions'][0]['*']);
				if ($fragtype !== false) {
					$fragtype['title'] = $entry['title'];
					$fragtypes[] = $fragtype;
				} else {
					$ignored[] = $entry['title'];
				}
			}
		}
		usort($fragtypes, 'fragmentLoaderTypePriorityCmp');
		return $fragtypes;
	}

}

// these functions have to be defined outside of the class --
// they are used as callbacks
function fragmentLoaderTypePriorityCmp($fragtype1, $fragtype2) {
	if($fragtype1['priority'] < $fragtype2['priority']) {
		return -1;
	} else if($fragtype1['priority'] > $fragtype2['priority']) {
		return 1;
	} else {
		return strcmp($fragtype1['title'], $fragtype2['title']);
	}
}
