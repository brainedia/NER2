<?php
namespace Features;


class DistanceSignalWords extends Plugin {
	
	
	/** Liste von Signalwörtern
	 */
	public static $signalWords = array(
		"kino",
		"film",
		"premiere",
		"guck",
		"schau"
	);


	/** Gibt den Datentyp für dieses Attribut zurück
	 * @return string
	 */
	public static function getDatatype() {
		return self::DATATYPE_NUMERIC;
	}


	/** Gibt der Attribut-Wert für das jeweilige Token zurück
	 * @return mixed
	 */
	public function getValueForToken( $token, array $tokens, $currentTokensIndex ) {
		$shortestDistance = null;
		foreach( $tokens as $index => $token ) {
			$isSignalWord = \rsCore\StringUtils::containsOne( $token, self::$signalWords );
			if( $isSignalWord ) {
				$distance = abs( $index - $currentTokensIndex );
				if( !$shortestDistance || $distance < $shortestDistance )
					$shortestDistance = $distance;
			}
		}
		return $shortestDistance;
	}


}