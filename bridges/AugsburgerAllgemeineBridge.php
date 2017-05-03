<?php

class AugsburgerAllgemeineBridge extends BridgeAbstract
{
	const NAME = 'Augsburger Allgemeine';
	const URI = 'http://www.augsburger-allgemeine.de/';
	const DESCRIPTION = 'Returns search results from www.augsburger-allgemeine.de';
	const MAINTAINER = 'bernd@berrnd.de';

	const PARAMETERS = array(
		'By search' => array(
			'searchterm' => array(
				'name' => 'Search term',
				'required' => true
			)
		)
	);

	public function getName()
	{
		switch($this->queriedContext)
		{
			case 'By search':
				return self::NAME . ' - search "' . $this->getInput('searchterm') . '"';
			default:
				return self::NAME;
		}
	}

	//http://simplehtmldom.sourceforge.net/manual_api.htm
	public function collectData()
	{
		switch($this->queriedContext)
		{
			case 'By search':
				$this->CollectBySearch();
				break;
		}
	}

	private function CollectBySearch()
	{
		$url = self::URI . 'suche/?num=20&q=' . urlencode($this->getInput('searchterm'));
		$page = getSimpleHTMLDOMCached($url) or returnServerError('Error requesting ' . $url);

		foreach($page->find('.search-result-item') as $searchResultDiv)
		{
			$item = array();

			$item['uri'] = $searchResultDiv->find('.result-item-title', 0)->children(0)->href;
			$item['title'] = $searchResultDiv->find('.result-item-title', 0)->children(0)->children(0)->innertext;
			$item['timestamp'] = strtotime($searchResultDiv->find('.result-item-date', 0)->innertext);
			$item['content'] = $searchResultDiv->outertext;

			$authorElement = $searchResultDiv->find('.result-item-author', 0);
			if ($authorElement !== null)
			{
				$item['author'] = $authorElement->innertext;
			}

			$this->items[] = $item;
		}

		$page->clear();
	}
}
