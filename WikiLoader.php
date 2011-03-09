<?php

class WikiLoader {
	const API = 'http://de.guttenplag.wikia.com/api.php';
	const PAGES_PER_QUERY = 50;

	// Returns a list of pages with a given prefix in unserialized format.
	// Gracefully resumes the API query if the result limit is exceeded.
	static private function queryPrefixList($prefix,
		$ignoreRedirects = false)
	{
		$url = self::API.'?action=query&prop=&format=php&generator=allpages&gaplimit=500&gapprefix='.urlencode($prefix);
		if ($ignoreRedirects)
			$url .= '&gapfilterredir=nonredirects';

		$s = unserialize(file_get_contents($url));

		while(isset($s['query-continue'])) {
			$url2 = $url.'&gapfrom='.urlencode($s['query-continue']['allpages']['gapfrom']);
			unset($s['query-continue']);
			$s = array_merge_recursive($s, unserialize(file_get_contents($url2)));
		}

		return $s;
	}

	// Returns a list of category members in unserialized format.
	// Gracefully resumes the API query if the result limit is exceeded.
	static private function queryCategoryMembers($category)
	{
		$url = self::API.'?action=query&list=categorymembers&cmtitle='.urlencode($category).'&format=php&cmlimit=500';
		$s = unserialize(file_get_contents($url));

		while(isset($s['query-continue'])) {
			$url2 = $url.'&cmcontinue='.urlencode($s['query-continue']['categorymembers']['cmcontinue']);
			unset($s['query-continue']);
			$s = array_merge_recursive($s, unserialize(file_get_contents($url2)));
		}

		return $s;
	}

	// Returns page data (given a list of page IDs) in unserialized format.
	static public function queryEntries($pageids)
	{
		return unserialize(file_get_contents(self::API.'?action=query&prop=info%7Crevisions%7Ccategories&rvprop=content&cllimit=max&format=php&pageids='.urlencode(implode('|', $pageids))));
	}

	// Returns page data (given a page title) in unserialized format.
	static private function queryEntryByTitle($title)
	{
		return unserialize(file_get_contents(self::API.'?action=query&prop=revisions&rvprop=content&format=php&titles='.urlencode($title)));
	}


	// Returns a list of page IDs of pages with a given prefix.
	static public function getPrefixList($prefix, $ignoreRedirects = false)
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
		foreach(array_chunk($pageids, self::PAGES_PER_QUERY) as $chunk) {
			$response = self::queryEntries($chunk);
			if(isset($response['query-continue']['categories']))
				error_log("Not all categories have been returned, reduce PAGES_PER_QUERY!");
			if(isset($response['query']['pages']))
				$entries = array_merge($entries, $response['query']['pages']);
		}

		if($ignoreRedirects) {
			$entries = array_filter($entries, 'wikiLoaderIsNonRedirect');
		}

		if($sortByTitle) {
			usort($entries, 'wikiLoaderTitleCmp');
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
		$pageids = self::getPrefixList($prefix, $ignoreRedirects);
		return self::getEntries($pageids, false, $sortByTitle);
	}
}

// these functions have to be defined outside of the class --
// they are used as callbacks
function wikiLoaderIsNonRedirect($entry) {
	return !isset($entry['redirect']);
}
function wikiLoaderTitleCmp($entry1, $entry2) {
	return strnatcasecmp($entry1['title'], $entry2['title']);
}
