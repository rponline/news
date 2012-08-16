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

namespace OCA\News;

// load SimplePie library
//TODO: is this a suitable place for the following require?
 require_once('news/3rdparty/SimplePie/autoloader.php');

class Utils {

	/**
	 * @brief Transform a date from UNIX timestamp format to MDB2 timestamp format
	 * @param dbtimestamp
	 * @returns
	 */
	public static function unixtimeToDbtimestamp($unixtime) {
		$dt = \DateTime::createFromFormat('U', $unixtime);
		return $dt->format('Y-m-d H:i:s');
	}

	/**
	 * @brief Transform a date from MDB2 timestamp format to UNIX timestamp format
	 * @param dbtimestamp
	 * @returns
	 */
	public static function dbtimestampToUnixtime($dbtimestamp) {
		$dt = \DateTime::createFromFormat('Y-m-d H:i:s', $dbtimestamp);
		return $dt->format('U');
	}

	/**
	 * @brief Fetch a feed from remote
	 * @param url remote url of the feed
	 * @returns an instance of OC_News_Feed
	 */
	public static function fetch($url){
		$spfeed = new \SimplePie_Core();
		$spfeed->set_feed_url( $url );
		$spfeed->enable_cache( false );

		if (!$spfeed->init()) {
			return null;
		}

	   //temporary try-catch to bypass SimplePie bugs
	   try {
		$spfeed->handle_content_type();
		$title = $spfeed->get_title();

		$items = array();
		if ($spitems = $spfeed->get_items()) {
			foreach($spitems as $spitem) {
				$itemUrl = $spitem->get_permalink();
				$itemTitle = $spitem->get_title();
				$itemGUID = $spitem->get_id();
				$itemBody = $spitem->get_content();
				$item = new Item($itemUrl, $itemTitle, $itemGUID, $itemBody);

				$itemAuthor = $spitem->get_author();
				if ($itemAuthor !== null) {
					$item->setAuthor($itemAuthor->get_name());
				}

				//date in Item is stored in UNIX timestamp format
				$itemDate = $spitem->get_date('U');
				$item->setDate($itemDate);

				$items[] = $item;
			}
		}

		$feed = new Feed($url, $title, $items);

		$favicon = $spfeed->get_image_url();

		if ($favicon !== null) { // use favicon from feed
			if(self::checkFavicon($favicon))
				$feed->setFavicon($favicon);
		}
		else { // try really hard to find a favicon
			if( null !== ($webFavicon = self::discoverFavicon($url)) )
				$feed->setFavicon($webFavicon);
		}
		return $feed;
		}
	   catch (Exception $e) {
		return null;
	   }
	}

	public static function checkFavicon($favicon) {
		$file = new \SimplePie_File($favicon);
		// size in bytes
		$filesize = strlen($file->body);

		if($file->success && $filesize > 0 && $filesize < 50000) {
			$sniffer = new \SimplePie_Content_Type_Sniffer($file);
			if(substr($sniffer->get_type(), 0, 6) === 'image/') {
				return true;
			}
		}
		return false;
	}

	public static function discoverFavicon($url) {
		//try webroot favicon
		$favicon = \SimplePie_Misc::absolutize_url('/favicon.ico', $url);

		if(self::checkFavicon($favicon))
			return $favicon;

		//try to extract favicon from web page
		$absoluteUrl = \SimplePie_Misc::absolutize_url('/', $url);

		$handle = curl_init ( );
		curl_setopt ( $handle, CURLOPT_URL, $absoluteUrl );
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
					if(self::checkFavicon($favicon))
						return $favicon;
				}
			}
		}
		return null;
	}
}