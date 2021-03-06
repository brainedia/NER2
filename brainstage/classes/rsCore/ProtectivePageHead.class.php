<?php
/**
 * @author Robert Sass <rs@brainedia.de>
 * @copyright 2014-2015 Robert Sass
 * @link http://www.brainedia.com
 * @link http://robertsass.me
 */

namespace rsCore;


/** BaseTemplate class.
 *
 * @author Robert Sass <rs@brainedia.de>
 * @copyright 2014-2015 Robert Sass
 *
 * @extends Observable
 */
class ProtectivePageHead implements ProtectivePageHeadInterface {


	private $_PageHead;


	/** Gibt das gewrappte PageHead-Objekt zurück
	 *
	 * @final
	 * @return \rsCore\PageHead
	 */
	final protected function getPageHead() {
		return $this->_PageHead;
	}


	/** Konstruktor
	 *
	 * @access public
	 * @final
	 * @param \rsCore\PageHead $PageHead
	 * @return void
	 */
	final public function __construct( \rsCore\PageHead $PageHead ) {
		$this->_PageHead = $PageHead;
	}


	/** Gibt eine geschützte Instanz, d.h. eine die keine Möglichkeiten zum Entfernen umfasst, zurück
	 *
	 * @access public
	 * @final
	 * @return \rsCore\ProtectivePageHead
	 */
	final public function getProtectiveInstance() {
		return $this;
	}


	/** Gibt den Titel zurück
	 *
	 * @access public
	 * @return string
	 */
	final public function getPagetitle() {
		return $this->getPageHead()->getPagetitle();
	}


	/** Ergänzt den Titel
	 *
	 * @access public
	 * @return object Selbstreferenz
	 */
	public function setPagetitle( $title ) {
		$title = $this->getPagetitle() .' - '. $title;
		$this->getPageHead()->setPagetitle( $title );
		return $this;
	}


	/** Gibt die verlinkten Stylesheets zurück
	 *
	 * @access public
	 * @return array
	 */
	final public function getStylesheets() {
		return $this->getPageHead()->getStylesheets();
	}


	/** Verlinkt ein Stylesheet
	 *
	 * @access public
	 * @param string $stylesheetPath Pfad zur CSS-Datei
	 * @return object Selbstreferenz
	 */
	public function linkStylesheet( $stylesheetPath, $media="all" ) {
		$this->getPageHead()->linkStylesheet( $stylesheetPath, $media );
		return $this;
	}


	/** Gibt die verlinkten Scripts zurück
	 *
	 * @access public
	 * @return array
	 */
	final public function getScripts() {
		return $this->getPageHead()->getScripts();
	}


	/** Verlinkt ein Script
	 *
	 * @access public
	 * @param string $scriptPath Pfad zur Javascript-Datei
	 * @return object Selbstreferenz
	 */
	public function linkScript( $scriptPath ) {
		$this->getPageHead()->linkScript( $scriptPath );
		return $this;
	}


	/** Gibt die verlinkten Metas zurück
	 *
	 * @access public
	 * @return array
	 */
	final public function getMetas() {
		return $this->getPageHead()->getMetas();
	}


	/** Fügt ein Meta-Name hinzu
	 *
	 * @access public
	 * @param string $name
	 * @param string $content
	 * @return object Selbstreferenz
	 */
	public function addMetaName( $name, $content ) {
		$this->getPageHead()->addMetaName( $name, $content );
		return $this;
	}


	/** Fügt ein Meta-HTTP-Equiv hinzu
	 *
	 * @access public
	 * @param string $httpEquiv
	 * @param string $content
	 * @return object Selbstreferenz
	 */
	public function addMetaHttpEquiv( $httpEquiv, $content ) {
		$this->getPageHead()->addMetaHttpEquiv( $httpEquiv, $content );
		return $this;
	}


	/** Gibt sonstige eingefügte Head-Inhalte zurück
	 *
	 * @access public
	 * @return array
	 */
	final public function getOthers() {
		return $this->getPageHead()->getOthers();
	}


	/** Fügt sonstigen Head-Inhalt ein
	 *
	 * @access public
	 * @param mixed $snippetOrContainerInstance
	 * @return object Selbstreferenz
	 */
	public function addOther( $snippetOrContainerInstance ) {
		$this->getPageHead()->addOther( $snippetOrContainerInstance );
		return $this;
	}


	/** Gibt die eingefügten Header-Links zurück
	 *
	 * @access public
	 * @return array
	 */
	final public function getLinks() {
		return $this->getPageHead()->getLinks();
	}


	/** Fügt einen Header-Link hinzu
	 *
	 * @access public
	 * @param string $rel
	 * @param string $href
	 * @param string $type
	 * @param string $language
	 * @return object Selbstreferenz
	 */
	public function addLink( $rel, $href, $type=null, $language=null ) {
		$this->getPageHead()->addLink( $rel, $href, $type, $language );
		return $this;
	}


}