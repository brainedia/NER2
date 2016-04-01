<?php
namespace Features;


class ContainsSignalWords extends Plugin {


	public static function getDatatype() {
		return self::DATATYPE_BOOL;
	}


	public function getValueForToken( $token, array $tokens, $currentTokensIndex ) {
		if( \rsCore\StringUtils::containsOne( $token, DistanceSignalWords::$signalWords ) )
			return 1;
		return 0;
	}


}