<?php

class AugsburgerAllgemeineBridge extends BridgeAbstract
{
	const NAME = 'Augsburger Allgemeine';
	const URI = 'https://www.augsburger-allgemeine.de/';
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
		$url = self::URI . 'suche/?q=' . urlencode($this->getInput('searchterm'));
		$searchResultsPage = getSimpleHTMLDOMCached($url) or returnServerError('Error requesting ' . $url);

		foreach($searchResultsPage->find('a[class=p-swatch_topdown-nice p-blocklink p-block p-marg_v-xl]') as $searchResultElement)
		{
			$articleUrl = $searchResultElement->getAttribute('href');
			$articlePage = getSimpleHTMLDOMCached($articleUrl) or returnServerError('Error requesting ' . $articleUrl);
			
			$item = array();
			$item['uri'] = $articleUrl;
			$item['title'] = $searchResultElement->getAttribute('title');			
			$item['timestamp'] = strtotime($articlePage->find('div[class=p-fnt_std-normal--xs p-marg_t-xl p-float_right]', 0)->innertext);
			$item['content'] = $articlePage->find('p[class=p-fnt_std-bold--ml]', 0)->innertext;

			$authorElement = $articlePage->find('a[class=b-cms-text__no-styles p-line-height--m]', 0);
			if ($authorElement !== null)
			{
				$item['author'] = $authorElement->getAttribute('title');
			}

			$this->items[] = $item;
		}

		$searchResultsPage->clear();
	}
}
