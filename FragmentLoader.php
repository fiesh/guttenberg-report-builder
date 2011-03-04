<?php

require_once('WikiLoader.php');

class FragmentLoader {
	static private function processString($s)
	{
		$needle = '';
		for($i = 1; $i < 12; $i++)
			$needle .= 'val_'.$i.'="([^"]*)"\s+';
		if (preg_match_all("/$needle/", $s, $a)) {
			for($i = 1; $i < 12; $i++) {
				$a[$i] = trim($a[$i][0]);
				if(strpos($a[$i], ',') !== false)
					$a[$i] = '"'.$a[$i].'"';
			}
			$a[0] = $s;
			return $a;
		} else {
			return false;
		}
	}

	static private function getFragmentsWithPrefix($prefix)
	{
		$entries = WikiLoader::getEntriesWithPrefix($prefix, true, true);
		$fragments = array();
		foreach($entries as $e) {
			$a = self::processString($e['revisions'][0]['*']);
			$a['wikiTitle'] = $e['title'];
			if(isset($a[1]) && $a[1])
				$fragments[] = $a;
		}
		return $fragments;
	}

	static public function getFragments()
	{
		$fragments = array();
		for($i = 0; $i < 5; $i++)
			$fragments = array_merge($fragments, self::getFragmentsWithPrefix("Fragment $i"));

		return $fragments;
	}
}
