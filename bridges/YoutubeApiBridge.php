<?php
define('YOUTUBEAPI_LIMIT', 10); // The default limit

class YoutubeApiBridge extends BridgeAbstract{
    
	private $request;
	
	public function loadMetadatas() {
	
		$this->maintainer = "logmanoriginal";
		$this->name = "Youtube API Bridge";
		$this->uri = "https://www.youtube.com/";
		$this->description = "Returns the newest videos by channel name or playlist id using the Youtube Data API";
		$this->update = "2016-01-09";
		
		$this->parameters["Get channel with limit"] =
		'[
			{
				"name" : "Youtube Data API key",
				"identifier" : "key"
			},
			{
				"name" : "Channel",
				"identifier" : "channel"
			},
			{
				"name" : "Limit",
				"identifier" : "limit",
				"type" : "number"
			}
		]';
		$this->parameters["Get playlist"] =
		'[
			{
				"name" : "Youtube Data API key",
				"identifier" : "key"
			},
			{
				"name" : "Playlist ID",
				"identifier" : "playlist_id"
			}
		]';
	
	}
    
	public function collectData(array $param){

		$limit = YOUTUBEAPI_LIMIT;
		$api = new StdClass(); // Cache for the YouTube Data API
		
		// Load the API key
		if (isset($param['key']) && $param['key'] != "") {
			$api->key = $param['key'];
		} else {
			$this->returnError('You must specify a valid API key (?key=...)', 400);
		}
		
		// Load number of feed items (limit)
		if (isset($param['channel']) && isset($param['limit']) && is_numeric($param['limit'])) {
			$limit = (int)$param['limit'];
		} 
		else if (isset($param['playlist_id'])) { 
			// not required
		} else {
			$limit = YOUTUBEAPI_LIMIT;
		}
		
		// Retrieve information by channel name
		if (isset($param['channel'])) {
			$this->request = $param['channel'];
			
			// We have to acquire the channel id first.
			// For some reason an error from the API results in a false from file_get_contents, so we've to handle that.
			$api->channels = file_get_contents('https://www.googleapis.com/youtube/v3/channels?part=contentDetails&forUsername=' . urlencode($this->request) . '&key=' . $api->key);
			if($api->channels == false) { 
				$this->returnError('Request failed! Check channel name and API key!', 400); 
			}
			$channels = json_decode($api->channels);
			
			// Calculate number of requests (max. 50 items per request possible)
			$req_limit = (int)($limit / 50);
			
			if($limit % 50 <> 0) {
				$req_limit++;
			}
			
			// Each page is identified by a page token, the first page has none.
			$pageToken = '';
			
			// Go through all pages
			for($i = 1; $i <= $req_limit; $i++){
				$api->playlistItems = file_get_contents('https://www.googleapis.com/youtube/v3/playlistItems?part=snippet%2CcontentDetails%2Cstatus&maxResults=50&playlistId=' . $channels->items[0]->contentDetails->relatedPlaylists->uploads . '&pageToken=' . $pageToken . '&key=' . $api->key);
				$playlistItems = json_decode($api->playlistItems);
				
				// Get the next token
				$pageToken = $playlistItems->nextPageToken;

				foreach($playlistItems->items as $element) {
					$item = new \Item();
					$item->id = $element->contentDetails->videoId;
					$item->uri = 'https://www.youtube.com/watch?v='.$item->id;
					$item->thumbnailUri = $element->snippet->thumbnails->{'default'}->url;
					$item->title = htmlspecialchars($element->snippet->title);
					$item->timestamp = strtotime($element->snippet->publishedAt);
					$item->content = '<a href="' . $item->uri . '"><img src="' . $item->thumbnailUri . '" /></a><br><a href="' . $item->uri . '">' . $item->title . '</a>';
					$this->items[] = $item;
					
					// Stop once the number of requested items is reached
					if(count($this->items) >= $limit) {
						break;
					}
				}
			}
		}
		
		// Retrieve information by playlist
		else if (isset($param['playlist_id'])) {
			
			// The title is not part of the playlist request, but can be requested separately. We have to display the correct playlist name properly.
			
			$api->playlists = file_get_contents('https://www.googleapis.com/youtube/v3/playlists?part=snippet&id=' . $param['playlist_id'] . '&key=' . $api->key);
			$playlists = json_decode($api->playlists);
			
			$this->request = htmlspecialchars($playlists->items[0]->snippet->title) . ' (' . htmlspecialchars($playlists->items[0]->snippet->channelTitle) . ')';
			
			// Reading playlist information is similar to how it works on a channel. We don't need a channel id though.
			// For a playlist we always return all items. YouTube has a limit of 200 items per playlist, so the maximum is 4 calls to the API.
			
			$pageToken = '';
			
			do {
				$api->playlistItems = file_get_contents('https://www.googleapis.com/youtube/v3/playlistItems?part=snippet%2CcontentDetails%2Cstatus&maxResults=50&playlistId=' . $param['playlist_id'] . '&pageToken=' . $pageToken . '&key=' . $api->key);
				$playlistItems = json_decode($api->playlistItems);

				foreach($playlistItems->items as $element) {
					$item = new \Item();
					$item->id = $element->contentDetails->videoId;
					$item->uri = 'https://www.youtube.com/watch?v='.$item->id;
					$item->thumbnailUri = $element->snippet->thumbnails->{'default'}->url;
					$item->title = htmlspecialchars($element->snippet->title);
					$item->timestamp = strtotime($element->snippet->publishedAt);
					$item->content = '<a href="' . $item->uri . '"><img src="' . $item->thumbnailUri . '" /></a><br><a href="' . $item->uri . '">' . $item->title . '</a>';
					$this->items[] = $item;
				}
				
				if (isset($playlistItems->nextPageToken)) {
					$pageToken = $playlistItems->nextPageToken;
				} else { 
					$pageToken = ''; 
				}
			} while ($pageToken != '');
		}
	}

	public function getName(){
		return (!empty($this->request) ? $this->request .' - ' : '') . 'Youtube API Bridge';
	}

	public function getURI(){
		return 'https://www.youtube.com/';
	}

	public function getCacheDuration(){
		return 10800; // 3 hours
	}
}
?>