<?php

class FragmentLoader {
	static private function processString($s)
	{
		$needle = '';
		for($i = 1; $i < 12; $i++)
			$needle .= 'val_'.$i.'="([^"]*)"\s+';
		preg_match_all("/$needle/", $s, $a);
		for($i = 0; $i < 12; $i++) {
			$a[$i] = trim(@$a[$i][0]);
			if(strpos($a[$i], ',') !== false)
				$a[$i] = '"'.$a[$i].'"';
		}
		return $a;
	}

	static private function getPrefixList($prefix)
	{
		return unserialize(file_get_contents('http://de.guttenplag.wikia.com/api.php?action=query&prop=revisions&&format=php&generator=allpages&gaplimit=500&gapprefix='.urlencode($prefix)));
	}

	static private function getEntries($pageids)
	{
		return unserialize(file_get_contents('http://de.guttenplag.wikia.com/api.php?action=query&prop=revisions&rvprop=content&format=php&pageids='.urlencode($pageids)));
	}

	static private function getFragmentsWithPrefix($prefix)
	{
		$polls = self::getPrefixList($prefix);
	
		$i = 0;
		$pageids = '';
		$fragments = array();
		foreach($polls['query']['pages'] as $page) {
			$pageids .= $page['pageid'].'|';
			if(++$i === 49) {
				$i = 0;
				$entries = self::getEntries($pageids);
				$fragments = array_merge($fragments, $entries['query']['pages']);
				$pageids = '';
			}
		}
		$entries = self::getEntries($pageids);
		if(isset($entries['query']['pages']))
			$fragments = array_merge($fragments, $entries['query']['pages']);

		$frags = array();
		foreach($fragments as $f) {
			$a = self::processString(@$f['revisions'][0]['*']);
			if(isset($a[1]) && $a[1])
				$frags[] = $a;
		}
		return $frags;
	}

	static public function getFragments()
	{
		$fragments = array();
		for($i = 0; $i < 5; $i++)
			$fragments = array_merge($fragments, self::getFragmentsWithPrefix("Fragment $i"));

		return $fragments;
	}
}
