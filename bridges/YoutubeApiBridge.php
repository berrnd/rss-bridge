<?php
define('YOUTUBEAPI_LIMIT', 10); // The default limit

class YoutubeApiBridge extends BridgeAbstract{

	private $request;
	
	public function loadMetadatas() {
	
		$this->maintainer = "logmanoriginal";
		$this->name = "Youtube API Bridge";
		$this->uri = "https://www.youtube.com/";
		$this->description = "Returns the newest videos by channel name or playlist id using the Youtube Data API";
		$this->update = "2016-08-15";
		
		$this->parameters['global'] =
		'[
			{
				"name" : "Youtube Data API key",
				"identifier" : "key",
				"type" : "text",
				"required" : true,
				"title" : "Insert your Data API key here!"
			},
			{
				"name" : "Limit",
				"identifier" : "limit",
				"type" : "number",
				"required" : false,
				"title" : "Specifies the number of items to return for each request",
				"defaultValue" : 10
			}
		]';

		$this->parameters['Get channel with limit'] =
		'[
			{
				"name" : "Channel",
				"identifier" : "channel",
				"type" : "text",
				"required" : true,
				"title" : "Insert channel name here!",
				"exampleValue" : "Youtube"
			}
		]';
		
		$this->parameters['Get channel by id with limit'] =
		'[
			{
				"name" : "Channel id",
				"identifier" : "channel_id",
				"type" : "text",
				"required" : true,
				"title" : "Insert channel id here!",
				"exampleValue" : ""
			}
		]';

		$this->parameters['Get playlist'] =
		'[
			{
				"name" : "Playlist ID",
				"identifier" : "playlist_id",
				"type" : "text",
				"required" : true,
				"title" : "Insert playlist ID here!",
				"exampleValue" : "PLbpi6ZahtOH5v1L8oiDSetlj5TTM7tY7N"
			}
		]';
	}

	public function collectData(array $param){
		if (!isset($param['key']) || empty($param['key']))
			$this->returnError('You must specify a valid API key (?key=...)', 400);
		
		$apiKey = $param['key'];

		if (!isset($param['limit']) || empty($param['limit']))
			$limit = YOUTUBEAPI_LIMIT;
		elseif (!is_numeric($param['limit']))
			$this->returnError('The limit you specified ("' . $limit . '") is not a valid number!', 400);
		else
			$limit = $param['limit'];

		if (isset($param['channel']) || isset($param['channel_id'])) { // Retrieve information by channel name or id
			//$this->name = $param['channel'] . ' - ' . $this->name;
			
			$query_param = 'forUsername';
            if (isset($param['channel_id']))
                $query_param = 'id';

			// We have to acquire the channel id first.
			$request = 'https://www.googleapis.com/youtube/v3/channels?part=contentDetails&' . $query_param . '=' . urlencode($param['channel'] ?: $param['channel_id']) . '&key=' . $apiKey;
			$json = file_get_contents($request);
			if($json === false)
				$this->returnError('Request failed for request: ' . $request . '!', 500); 

			$channels = json_decode($json);
			$playlistId = $channels->items[0]->contentDetails->relatedPlaylists->uploads;
			$this->add_playlist($playlistId, $apiKey, $limit);
		}
		
		else if (isset($param['playlist_id'])) { // Retrieve information by playlist
			$playlistId = $param['playlist_id'];
			
			// Reading playlist information is similar to how it works on a channel. We don't need a channel id though.
			// For a playlist we always return all items. YouTube has a limit of 200 items per playlist, so the maximum is 4 calls to the API.
			$this->add_playlist($playlistId, $apiKey, $limit);
		}
	}

	private function add_playlist($playlistId, $apiKey, $limit = -1){
		// The title is not part of the playlist request, but can be requested separately. We have to display the correct playlist name properly.
		$request = 'https://www.googleapis.com/youtube/v3/playlists?part=snippet&id=' . $playlistId . '&key=' . $apiKey;
		$json = file_get_contents($request);
		if($json === false)
			$this->returnError('Request failed for request: ' . $request . '!', 500); 

		$playlists = json_decode($json);
		$this->name = htmlspecialchars($playlists->items[0]->snippet->title) . ' - ' . $this->name;

		$pageToken = '';

		do {
			$request = 'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet%2CcontentDetails%2Cstatus&maxResults=50&playlistId=' . $playlistId . '&pageToken=' . $pageToken . '&key=' . $apiKey;
			$json = file_get_contents($request);
			if($json === false)
				$this->returnError('Request failed for request: ' . $request . '!', 500); 

			$playlistItems = json_decode($json);

			$done = $this->add_playlist_items($playlistItems, $limit);

			if (!$done && isset($playlistItems->nextPageToken))
				$pageToken = $playlistItems->nextPageToken;
			else
				break;
		} while ($pageToken !== '');
	}

	private function add_playlist_items($playlistItems, $limit = -1){
		foreach($playlistItems->items as $element) {
			// Store description in a temporary variable, the description might 
			// consist of multiple paragraphs and can include hyperlinks:
			$description = htmlspecialchars($element->snippet->description);
			$description = nl2br($description);
			// Todo: This regex does not cover the RFC3987, but it works for basic ones (no data and such)
			$description = preg_replace('/(http[s]{0,1}\:\/\/[a-zA-Z0-9.\/]{4,})/ims', '<a href="$1" target="_blank">$1</a> ', $description);

			$thumbnail = $element->snippet->thumbnails->{'medium'}->url;

			$item = new \Item();
			$item->id = $element->contentDetails->videoId;
			$item->author = $element->snippet->channelTitle;
			$item->uri = 'https://www.youtube.com/watch?v=' . $element->contentDetails->videoId;
			$item->title = htmlspecialchars($element->snippet->title);
			$item->timestamp = strtotime($element->snippet->publishedAt);
			$item->content = '<div>'
                        . '<a href="' . $item->uri . '"><img width=320" height="180" align="left" style="padding-right: 10px; padding-bottom: 10px;" src="' . $thumbnail . '" /></a>'
                        . nl2br(htmlentities($element->snippet->description))
                        . '<br><br><a href="' . $item->uri . '">' . $item->uri . '</a>'
                        . '</div>';
			$this->items[] = $item;

			// Stop once the number of requested items is reached (<= 0: all), return true if done
			if(count($this->items) >= $limit && $limit >= 1)
				return true;
		}
	}
}
