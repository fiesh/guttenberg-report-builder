<?php

class WikiLoader {
	const API = 'http://de.guttenplag.wikia.com/api.php';
	const REDIRECT_PATTERN = '/^#(REDIRECT|WEITERLEITUNG)\s+/';

	// Returns a list of pages with a given prefix in unserialized format.
	static private function queryPrefixList($prefix)
	{
		return unserialize(file_get_contents(self::API.'?action=query&prop=revisions&&format=php&generator=allpages&gaplimit=500&gapprefix='.urlencode($prefix)));
	}

	// Returns a list of category members in unserialized format.
	static private function queryCategoryMembers($category)
	{
		return unserialize(file_get_contents(self::API.'?action=query&list=categorymembers&cmtitle='.urlencode($category).'&format=php&cmlimit=500'));
	}

	// Returns page data (given a list of page IDs) in unserialized format.
	static private function queryEntries($pageids)
	{
		return unserialize(file_get_contents(self::API.'?action=query&prop=revisions&rvprop=content&format=php&pageids='.urlencode(implode('|', $pageids))));
	}

	// Returns page data (given a page title) in unserialized format.
	static private function queryEntryByTitle($title)
	{
		return unserialize(file_get_contents(self::API.'?action=query&prop=revisions&rvprop=content&format=php&titles='.urlencode($title)));
	}


	// Returns a list of page IDs of pages with a given prefix.
	static public function getPrefixList($prefix)
	{
		$s = self::queryPrefixList($prefix);
		$pageids = array();
		foreach($s['query']['pages'] as $page) {
			$pageids[] = $page['pageid'];
		}
		return $pageids;
	}

	// Returns a list of page IDs of category members.
	static public function getCategoryMembers($category)
	{
		$s = self::queryCategoryMembers($category);
		$pageids = array();
		foreach($s['query']['categorymembers'] as $member) {
			$pageids[] = $member['pageid'];
		}
		return $pageids;
	}

	// Returns page data for a single page ID, in unserialized format.
	static public function getEntry($pageid)
	{
		return self::queryEntries(array($pageid));
	}

	// Returns raw Wikitext for a single page ID.
	static public function getRawText($pageid)
	{
		$s = self::queryEntries(array($pageid));
		return $s['query']['pages'][$pageid]['revisions'][0]['*'];
	}

	// Returns raw Wikitext for a single page, given the page title.
	static public function getRawTextByTitle($title)
	{
		$s = self::queryEntryByTitle($title);
		foreach ($s['query']['pages'] as $page)
			return $page['revisions'][0]['*'];
		return false;
	}

	// Returns page data for multiple page IDs, in unserialized format.
	// Optionally cleans up the returned data (removing results from
	// pages that are redirects; sorting results by page title).
	static public function getEntries($pageids,
		$ignoreRedirects = false, $sortByTitle = true)
	{
		$entries = array();
		foreach(array_chunk($pageids, 49) as $chunk) {
			$response = self::queryEntries($chunk);
			if(isset($response['query']['pages']))
				$entries = array_merge($entries, $response['query']['pages']);
		}

		if($ignoreRedirects) {
			$temp = array(); // will contain all non-redirects
			foreach($entries as $e) {
				if(!preg_match(self::REDIRECT_PATTERN, $e['revisions'][0]['*']))
					$temp[] = $e;
			}
			$entries = $temp;
		}

		if($sortByTitle) {
			$temp = array(); // will contain all wiki titles
			foreach($entries as $e)
				$temp[] = $e['title'];
			array_multisort($temp, $entries); // sort by wiki title
		}

		return $entries;
	}

	// Returns page data for all pages whose title starts with the given
	// prefix, in unserialized format.
	// Optionally cleans up the returned data (removing results from
	// pages that are redirects; sorting results by page title).
	static public function getEntriesWithPrefix($prefix,
		$ignoreRedirects = false, $sortByTitle = true)
	{
		$pageids = self::getPrefixList($prefix);
		return self::getEntries($pageids, $ignoreRedirects, $sortByTitle);
	}
}
