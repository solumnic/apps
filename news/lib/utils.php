<?php
/**
* ownCloud - News app
*
* @author Alessandro Cosentino
* Copyright (c) 2012 - Alessandro Cosentino <cosenal@gmail.com>
*
* This file is licensed under the Affero General Public License version 3 or later.
* See the COPYING-README file
*
*/

// load SimplePie library
//TODO: is this file a suitable place for the following require?
 require_once('news/3rdparty/SimplePie/autoloader.php');

class OC_News_Utils {

	/**
	 * @brief Fetch a feed from remote
	 * @param url remote url of the feed
	 * @returns
	 */
	public static function fetch($url){
	//TODO: handle the case where fetching of the feed fails
		$spfeed = new SimplePie_Core();
		$spfeed->set_feed_url( $url );
		$spfeed->enable_cache( false );
		$spfeed->init();
		$spfeed->handle_content_type();
		$title = $spfeed->get_title();

		$spitems = $spfeed->get_items();
		$items = array();
		foreach($spitems as $spitem) { //FIXME: maybe we can avoid this loop
			$itemUrl = $spitem->get_permalink();
			$itemTitle = $spitem->get_title();
			$itemGUID = $spitem->get_id();
			$itemBody = $spitem->get_content();
			$items[] = new OC_News_Item($itemUrl, $itemTitle, $itemGUID, $itemBody);
		}

		$feed = new OC_News_Feed($url, $title, $items);

		$favicon = $spfeed->get_image_url();
		//check if this file exists and the size with getimagesize()

		if ($favicon == null) { //try really hard to find a favicon
			if( null !== ($webFavicon = OC_News_Utils::discoverFavicon($url)) )
				$feed->setFavicon($webFavicon);
		}
		else { //use favicon from feed
			if(OC_News_Utils::checkFavicon($favicon))
				$feed->setFavicon($favicon);
		}
		return $feed;
	}

	public static function checkFavicon($favicon) {
		$file = new SimplePie_File($favicon);

		//TODO additional checks?
		if($file->success && strlen($file->body) > 0) {
			$sniffer = new SimplePie_Content_Type_Sniffer($file);
			if(substr($sniffer->get_type(), 0, 6) === 'image/') {
				return true;
			}
		}
		return false;
	}

	public static function discoverFavicon($url) {
		//try webroot favicon
		$favicon = SimplePie_Misc::absolutize_url('/favicon.ico', $url);

		if(OC_News_Utils::checkFavicon($favicon))
			return $favicon;

		//try to extract favicon from web page
		$handle = curl_init ( );
		curl_setopt ( $handle, CURLOPT_URL, $url );
		curl_setopt ( $handle, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt ( $handle, CURLOPT_FOLLOWLOCATION, TRUE );
		curl_setopt ( $handle, CURLOPT_MAXREDIRS, 10 );

		if ( FALSE!==($page=curl_exec($handle)) ) {
			preg_match ( '/<[^>]*link[^>]*(rel=["\']icon["\']|rel=["\']shortcut icon["\']) .*href=["\']([^>]*)["\'].*>/iU', $page, $match );
			if (1<sizeof($match)) {
				// the specified uri might be an url, an absolute or a relative path
				// we have to turn it into an url to be able to display it out of context
				$favicon = htmlspecialchars_decode ( $match[2] );
				// test for an url
				if (parse_url($favicon,PHP_URL_SCHEME)) {
					if(OC_News_Utils::checkFavicon($favicon))
						return $favicon;
				}
				// test for an absolute path
				elseif ( 0===strpos(parse_url($favicon,PHP_URL_PATH),'/') ) {
					$url_token = parse_url($meta['final']);
					sprintf( '%s://%s/%s', $url_token['scheme'], $url_token['host'], $favicon );
					if(OC_News_Utils::checkFavicon($favicon))
						return $favicon;
				}
				// so it appears to be a relative path
				else {
					$url_token = parse_url($meta['final']);
					sprintf( '%s://%s%s%s', $url_token['scheme'], $url_token['host'], dirname($url_token['path']), $favicon );
					if(OC_News_Utils::checkFavicon($favicon))
						return $favicon;
				}
			}
		}
		return null;
	}
}