<?php
/**
 * @author Robert Sass <rs@brainedia.de>
 * @copyright 2016 Robert Sass
 * @link http://www.brainedia.com
 * @link http://robertsass.me
 */

namespace Plugins;


/**
 * @author Robert Sass <rs@brainedia.de>
 * @copyright 2016 Robert Sass
 * @internal
 */
interface CrawlerInterface {
}


/** CrawlerPlugin
 *
 * @author Robert Sass <rs@brainedia.de>
 * @copyright 2016 Robert Sass
 *
 * @extends \rsCore\Plugin
 */
class Crawler extends \rsCore\Plugin implements CrawlerInterface, \Brainstage\Plugins\Dashboard\PluginInterface, \Brainstage\CronPluginInterface {

/* Framework Registration */

	/** Wird von Brainstage aufgerufen, damit sich das Plugin für Hooks registrieren kann
	 *
	 * @param \rsCore\FrameworkInterface $Framework
	 */
	public static function brainstageRegistration( \rsCore\FrameworkInterface $Framework ) {
		$Plugin = self::instance();
		$Framework->registerHook( $Plugin, 'getNavigatorItem' );
	}


	/** Wird von der API aufgerufen, damit sich das Plugin für Hooks registrieren kann
	 *
	 * @param \rsCore\FrameworkInterface $Framework
	 */
	public static function apiRegistration( \rsCore\FrameworkInterface $Framework ) {
		$Plugin = self::instance();
		$Framework->registerHook( $Plugin, 'review_search', 'api_reviewSearch' );
		$Framework->registerHook( $Plugin, 'review_grab', 'api_reviewGrab' );
	}


	/** Wird vom Cron aufgerufen, damit das Plugin seine Cronjobs registrieren kann
	 *
	 * @param \Brainstage\Cron $Cron
	 */
	public static function cronRegistration( \Brainstage\Cron $Cron ) {
		set_time_limit(300);
		$Plugin = self::instance();
	#	$Cron->register( $Plugin, 'crawlMovieTitles', 1 );
		$Cron->register( $Plugin, 'grabReviews', 10 );
		$Cron->register( $Plugin, 'crawlReviews', 160 );
	}


	/** Wird vom Brainstage-Plugin Dashboard aufgerufen, damit sich das Plugin für Hooks registrieren kann
	 *
	 * @param \rsCore\FrameworkInterface $Framework
	 */
	public static function dashboardRegistration( \rsCore\FrameworkInterface $Framework ) {
		$Plugin = self::instance();
		$Framework->registerHook( $Plugin, 'buildWidget' );
	}


/* General */

	/** Gibt den Pagination-Index zurück
	 * @return \Brainstage\Setting
	 */
	protected function getPaginationIndexSetting() {
		return $this->getMixedSetting( 'PaginationIndex', true );
	}


	/** Gibt die letzte iterierte Movie ID zurück
	 * @return \Brainstage\Setting
	 */
	protected function getMovieIdSetting() {
		return $this->getMixedSetting( 'ReviewSearch_MovieId', true );
	}


	/** Gibt die letzte iterierte ReviewSearch ID zurück
	 * @return \Brainstage\Setting
	 */
	protected function getReviewSearchIdSetting() {
		return $this->getMixedSetting( 'ReviewGrab_ReviewSearchId', true );
	}


/* Dashboard Widget */

	/** Gibt den Titel des Dashboard-Widgets zurück
	 * @return string
	 */
	public static function getDashboardWidgetTitle() {
		return self::t("Crawler");
	}


	/** Baut das Widget
	 * @param \rsCore\Container $Container
	 */
	public function buildWidget( \rsCore\Container $Container ) {
		$Row = $Container->subordinate( 'div.row' );

		$Counter = $Row->subordinate( 'div.col-xs-4 > div.huge-counter.centered' )
			->subordinate( 'span.number', \Site\CrawlerMovie::totalCount() )
			->append( 'span.description', self::t("Movie titles") );

		$Counter = $Row->subordinate( 'div.col-xs-4 > div.huge-counter.centered' )
			->subordinate( 'span.number', \Site\CrawlerReviewSearch::totalCount() )
			->append( 'span.description', self::t("Google results") );

		$Counter = $Row->subordinate( 'div.col-xs-4 > div.huge-counter.centered' )
			->subordinate( 'span.number', \Site\CrawlerReview::totalCount() )
			->append( 'span.description', self::t("Reviews") );
	}


/* Brainstage Plugin */

	/** Ergänzt den Navigator
	 * @return string
	 */
	public function getNavigatorItem() {
		return self::t("Crawler");
	}


/* API Plugin */

	/** Searches for reviews
	 * @return array
	 */
	public function api_reviewSearch( $params ) {
		$movieTitle = $params['movie'];
		if( $movieTitle )
			return $this->reviewSearch( $movieTitle );
		return $this->crawlReviews();
	}


	/** Iterates review search results and grabs the reviews
	 * @return array
	 */
	public function api_reviewGrab( $params ) {
		return $this->grabReviews();
	}


/* Cronjobs */

	/** Iterates for Movie titles
	 * @return array
	 */
	public function crawlMovieTitles() {
		$matches1 = $this->crawlMovieTitlesFromKinoDe();
		$matches2 = $this->crawlMovieTitlesFromKinoDe();
		$matches3 = $this->crawlMovieTitlesFromKinoDe();
		$matches4 = $this->crawlMovieTitlesFromKinoDe();
		return array_merge( $matches1, $matches2, $matches3, $matches4 );
	}


	/** Iterates movie titles and performs review searches
	 * @return array
	 */
	public function crawlReviews() {
		$MovieIdSetting = $this->getMovieIdSetting();
		$Movie = \Site\CrawlerMovie::getById( intval( $MovieIdSetting->value ) );

		$results = $this->reviewSearch( $Movie->title, 4 );
		foreach( $results as $url => $title ) {
			if( strlen( $url ) > 5 )
				$ReviewSearch = \Site\CrawlerReviewSearch::add( $Movie->getPrimaryKeyValue(), $title, $url );
		}

		if( !empty( $results ) && $ReviewSearch ) {
			$MovieIdSetting->value = intval( $MovieIdSetting->value )+1;
			$MovieIdSetting->adopt();
		}

		return $results;
	}


	/** Iterates review search results and grabs the reviews
	 * @return array
	 */
	public function grabReviews() {
		$queries = 5;

		$ReviewSearchIdSetting = $this->getReviewSearchIdSetting();

		$result = array();
		for( $i=0; $i<$queries; $i++ ) {
			$ReviewSearch = \Site\CrawlerReviewSearch::getById( intval( $ReviewSearchIdSetting->value ) );
			if( $ReviewSearch ) {
				$dataset = $this->grabReview( $ReviewSearch );
				if( !empty( $dataset ) ) {
					$Review = \Site\CrawlerReview::add( $ReviewSearch->getMovie(), $ReviewSearch );
					if( $Review ) {
						$Review->title = $dataset->title;
						$Review->text = $dataset->content;

						$result[] = $Review->getColumns();
					}
				}
			}
// 			if( $ReviewSearch ) {
				$ReviewSearchIdSetting->value = intval( $ReviewSearchIdSetting->value )+1;
				$ReviewSearchIdSetting->adopt();
// 			}
		}
		return $result;
	}


/* Crawler */

	/** Iterates for Movie titles in Kino.de
	 * @return array
	 */
	public function crawlMovieTitlesFromKinoDe() {
		$PaginationIndex = $this->getPaginationIndexSetting();
		$url = 'http://www.kino.de/filme/alphabet/page/'. intval( $PaginationIndex->value ) .'/?sp_country=deutschland,usa&sp_critic_ratings=2,3,4,5';

		$Request = \rsCore\Curl::get( $url );
		$response = $Request->getResponse();

		$regex = '#<h3 class="teaser-name movie"><a class="movie-link" href="(.*?)">(.*?)</a>#';
		$matches = array();
		preg_match_all( $regex, $response, $matches );
		$urls = $matches[1];
		$titles = $matches[2];
		$movies = array_combine( $urls, $titles );

		foreach( $movies as $url => $title ) {
			$Movie = \Site\CrawlerMovie::add( $title, $url );
		}

		if( !empty( $movies ) ) {
			$PaginationIndex->value = $PaginationIndex->value +1;
			$PaginationIndex->adopt();
		}
		return $movies;
	}


	/** Searches for reviews
	 * @return integer
	 */
	public function reviewSearch( $movieTitle, $queries=1 ) {
		$i = 0;
		$matches = array();
		for( $i=0; $i<$queries; $i++ ) {
			$result = $this->google( $movieTitle, $i );
			if( empty( $result ) )
				break;
			if( !is_array( $result ) )
				$result = array( $result );
			$matches = array_merge( $matches, $result );
			if( $i < $queries-1 )
				sleep(mt_rand(5,20));
		}
		return $matches;
	}


	/** Performs a Google search
	 * @return array
	 */
	public function google( $movieTitle, $paginationIndex=0 ) {
		$language = 'de';
		$optionalKeywords = array('kino', 'film', 'kritik');
		$excludeKeywords = array('trailer');
		$resultsPerPage = 8;

		$url = 'http://google.brainedia2.de/ajax/services/search/web?v=1.0&rsz='. $resultsPerPage .'&hl=*LANGUAGE*&*PAGINATION_START*&q=*COMPLEX_QUERY*&userip=*USERIP*';
		$url = str_replace( '*LANGUAGE*', $language, $url );
		$url = str_replace( '*PAGINATION_START*', $paginationIndex == 0 ? '' : 'start='. ($paginationIndex * $resultsPerPage), $url );
		$url = str_replace( '*QUERY_STRING*', urlencode( $movieTitle ), $url );
		$url = str_replace( '*USERIP*', mt_rand(10,250).'.'.mt_rand(10,250).'.'.mt_rand(10,250).'.'.mt_rand(10,250), $url );
		$url = str_replace( '*HINTS*', urlencode( implode( ' ', $optionalKeywords ) ), $url );

		$complexQuery = array();
		$complexQuery[] = '"'. $movieTitle .'"';
		$complexQuery[] = implode( ' OR ', $optionalKeywords );
		if( !empty( $excludeKeywords ) )
			$complexQuery[] = ' -'. implode( ' -', $excludeKeywords );
		$complexQuery = implode( ' ', $complexQuery );
		$url = str_replace( '*COMPLEX_QUERY*', urlencode( $complexQuery ), $url );

		$Request = \rsCore\Curl::get( $url );
		$Request->setUseragent( 'Mozilla/5.0 (iPod; CPU iPhone OS 8_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12B410 Safari/600.1.4' );
		$Response = $Request->send();
		$resultJson = $Response->getJson();

		if( $Response->getStatus() != 200 ) {
		#	$source = str_replace( '"/', '"'. $host .'/', $Response->getResponse() );
		#	$source = str_replace( 'action="', 'action="'. $host .'/', $source );
			echo $url;
			die( $Response->getResponse() );
		}

		$results = array();
		if( is_object( $resultJson ) ) {
			foreach( $resultJson->responseData->results as $resultSet ) {
				$results[ $resultSet->url ] = html_entity_decode( $resultSet->titleNoFormatting );
			}
		}

		return $results;
	}


	/** Performs a Google search
	 * @return array
	 */
	public function frameGoogleResults( $movieTitle, $paginationIndex=0 ) {
		$language = 'de';
		$optionalKeywords = array('kino', 'film', 'kritik');
		$resultsPerPage = 10;
		$host = 'http://www.google.de';

		$url = $host .'/search?hl=*LANGUAGE*&as_q=&as_epq=*QUERY_STRING*&as_oq=*HINTS*&as_eq=&as_nlo=&as_nhi=&lr=lang_*LANGUAGE*&cr=&as_qdr=all&as_sitesearch=&as_occt=any&safe=images&as_filetype=&as_rights=&*PAGINATION_START*';
		$url = str_replace( '*QUERY_STRING*', urlencode( $movieTitle ), $url );
		$url = str_replace( '*HINTS*', urlencode( implode( ' ', $optionalKeywords ) ), $url );
		$url = str_replace( '*LANGUAGE*', $language, $url );
		$url = str_replace( '*PAGINATION_START*', $paginationIndex == 0 ? '' : 'start='. ($paginationIndex * $resultsPerPage), $url );

		$Request = \rsCore\Curl::get( $url );
		$Request->setUseragent( 'Mozilla/5.0 (iPod; CPU iPhone OS 8_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12B410 Safari/600.1.4' );
		$Response = $Request->send();
		$responseSource = $Response->getResponse();

		if( $Response->getStatus() == 503 ) {
			$source = str_replace( '"/', '"'. $host .'/', $Response->getResponse() );
		#	$source = str_replace( 'action="', 'action="'. $host .'/', $source );
			die( $source );
		}

		$regex = '/<div class="g.*?<a href="(.*?)".*?>(.*?)<\/a>/';
		$matches = array();
		preg_match_all( $regex, $responseSource, $matches );
		$urls = $matches[1];
		$titles = $matches[2];

		$results = array();
		foreach( array_combine( $urls, $titles ) as $url => $title ) {
			$regex = '#\/url\?q=(.*?)&#';
			$matches = array();
			preg_match( $regex, $url, $matches );
			$url = rawurldecode( $matches[1] );

			$title = \rsCore\StringUtils::getPlainText( $title );
			$results[ $url ] = $title;
		}

/*
		if( !empty( $movies ) ) {
			$PaginationIndex->value = $PaginationIndex->value +1;
			$PaginationIndex->adopt();
		}
*/
		return $results;
	}


	/** Searches for reviews
	 * @return integer
	 */
	public function grabReview( \Site\CrawlerReviewSearch $ReviewSearch ) {
		$token = 'befd7e4a460b2a998c9ba784af1de52f12726781';
		$url = 'http://readability.com/api/content/v1/parser?url='. urlencode( $ReviewSearch->url ) .'&token='. $token;

		$Request = \rsCore\Curl::get( $url );
		$Request->setUseragent( 'Mozilla/5.0 (iPod; CPU iPhone OS 8_1 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12B410 Safari/600.1.4' );
		$Response = $Request->send();
		$resultJson = $Response->getJson();

		if( $Response->getStatus() != 200 ) {
		#	$source = str_replace( '"/', '"'. $host .'/', $Response->getResponse() );
		#	$source = str_replace( 'action="', 'action="'. $host .'/', $source );
		#	echo $ReviewSearch->url ."\n";
		#	echo $url;
		#	die( $source );
			return array();
		}

		return $resultJson;
	}


}