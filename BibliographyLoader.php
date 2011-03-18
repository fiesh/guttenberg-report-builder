<?php

require_once('WikiLoader.php');

class BibliographyLoader {

	static private function parseSource($rawText)
	{
		if(preg_match_all('/{{Quelle(.*)}}/s', $rawText, $matches) === 1) {
			$text = $matches[1][0];
			preg_match_all('/|\s*(\w+)\s*=\s*([^|]+)/', $text, $matches);
			$i = 0;
			$source = array();
			while(isset($matches[1][$i])) {
				if($matches[1][$i]) {
					$source[$matches[1][$i]] = trim($matches[2][$i]);
				}
				$i++;
			}
			return $source;
		} else {
			return false;
		}
	}

	static public function getSources(&$ignored = array())
	{
		$pageids = WikiLoader::getCategoryMembers('Kategorie:Quelle');
		$entries = WikiLoader::getEntries($pageids, true, true);

		$sources = array();
		foreach($entries as $entry) {
			$source = self::parseSource($entry['revisions'][0]['*']);
			if ($source !== false) {
				$source['title'] = $entry['title'];
				$sources[] = $source;
			} else {
				$ignored[] = $entry['title'];
			}
		}
		return $sources;
	}

}
