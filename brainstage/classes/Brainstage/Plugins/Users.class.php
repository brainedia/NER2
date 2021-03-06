<?php
/**
 * @author Robert Sass <rs@brainedia.de>
 * @copyright 2014-2015 Robert Sass
 * @link http://www.brainedia.com
 * @link http://robertsass.me
 */

namespace Brainstage\Plugins;


/**
 * @author Robert Sass <rs@brainedia.de>
 * @copyright 2014-2015 Robert Sass
 * @internal
 */
interface UsersInterface extends PluginInterface {
}


/** UsersPlugin
 *
 * @author Robert Sass <rs@brainedia.de>
 * @copyright 2014-2015 Robert Sass
 *
 * @extends \rsCore\Plugin
 */
class Users extends \Brainstage\Plugin implements UsersInterface {


	const DEFAULT_INTERVAL_SIZE = null;
	const PASSWORD_POLICY_MIN_LENGTH = 8;
	const PASSWORD_POLICY_REGEX = '^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])[0-9a-zA-Z]{8,}$';
	const PASSWORD_POLICY_DESCRIPTION = 'The password must be at least 8 characters long and contain both upper and lower letters as well as numbers.';


	/** Wird von Brainstage aufgerufen, damit sich das Plugin für Hooks registrieren kann
	 *
	 * @param Framework $Framework
	 */
	public static function brainstageRegistration( \rsCore\FrameworkInterface $Framework ) {
		$Plugin = self::instance();
		$Framework->registerHook( $Plugin, 'buildHead' );
		$Framework->registerHook( $Plugin, 'buildBody' );
		$Framework->registerHook( $Plugin, 'getNavigatorItem' );
	}


	/** Wird von Brainstage aufgerufen, damit sich das Plugin in die Menüreihenfolge einsortieren kann
	 * @return int Desto höher der Wert, desto weiter oben erscheint das Plugin
	 */
	public static function brainstageSortValue() {
		return 80;
	}


	/** Wird von der API aufgerufen, damit sich das Plugin für Hooks registrieren kann
	 *
	 * @param \rsCore\FrameworkInterface $Framework
	 */
	public static function apiRegistration( \rsCore\FrameworkInterface $Framework ) {
		$Plugin = self::instance();
		$Framework->registerHook( $Plugin, 'create', 'api_createUser' );
		$Framework->registerHook( $Plugin, 'list', 'api_listUsers' );
		$Framework->registerHook( $Plugin, 'delete', 'api_deleteUser' );
		$Framework->registerHook( $Plugin, 'save', 'api_saveUser' );
		$Framework->registerHook( $Plugin, 'groups-create', 'api_createGroup' );
		$Framework->registerHook( $Plugin, 'groups-list', 'api_listGroups' );
		$Framework->registerHook( $Plugin, 'groups-delete', 'api_deleteGroup' );
		$Framework->registerHook( $Plugin, 'groups-save', 'api_saveGroup' );
		$Framework->registerHook( $Plugin, 'rights-list', 'api_listRights' );
	}


	/** Wird von Brainstage aufgerufen, um abzufragen, welche Rechtebezeichner vom Plugin verwendet werden
	 *
	 * @return array
	 */
	public static function registerPrivileges() {
		return 'editUser,createUser,deleteUser,editGroup,createGroup,deleteGroup';
	}


	/** Dient als Konstruktor-Erweiterung
	 */
	protected function init() {
	}


/* Private methods */

	protected function getListIntervalSize() {
		return intval( getVar( 'limit', self::DEFAULT_INTERVAL_SIZE ) );
	}


	protected function getPaginationMax() {
		return ceil( $this->getFileManager()->countUsersFiles() / $this->getListIntervalSize() );
	}


	protected function getPaginationIndex() {
		return getVar( 'page', 1 );
	}


	protected function getUsers( $start=0, $limit=self::DEFAULT_INTERVAL_SIZE ) {
		return \Brainstage\User::getUsers( $limit, $start );
	}


	protected function getGroups( $start=0, $limit=self::DEFAULT_INTERVAL_SIZE ) {
		return \Brainstage\Group::getGroups( $limit, $start );
	}


	protected static function getRights( $onlyOwnRights=false ) {
		if( $onlyOwnRights ) {
			$ownRights = array();
			if( !user()->isSuperAdmin() ) {
				foreach( user()->getRights() as $Right )
					$ownRights[] = $Right->key;
			}
		}

		$rights = array();
		if( !$onlyOwnRights || user()->isSuperAdmin() )
			$rights[ 'Brainstage:superadmin' ] = 'boolean';
		foreach( \Brainstage\Brainstage::getPluginPrivileges() as $pluginName => $privileges ) {
			$pluginName = ltrim( $pluginName, '\\' );
			$pluginName = str_replace( '\\', '/', $pluginName );

			if( !$onlyOwnRights || user()->isSuperAdmin() || in_array( $pluginName, $ownRights ) )
				$rights[ $pluginName ] = 'boolean';

			if( is_array( $privileges ) ) {
				foreach( $privileges as $privilege => $type ) {
					$privilegeDescriptor = $pluginName .':'. $privilege;
					if( !$onlyOwnRights || user()->isSuperAdmin() || in_array( $privilegeDescriptor, $ownRights ) )
						$rights[ $privilegeDescriptor ] = $type;
				}
			}
		}
		return $rights;
	}


/* Brainstage Plugin */

	/** Ergänzt den Header
	 * @param \rsCore\ProtectivePageHead $Head
	 */
	public function buildHead( \rsCore\ProtectivePageHead $Head ) {
		$Head->linkScript('static/js/users.js');
	}


	/** Ergänzt den Navigator
	 * @return string
	 */
	public function getNavigatorItem() {
		return self::t("Users");
	}


	/** Ergänzt den MainContent
	 * @param \rsCore\Container $Container
	 */
	public function buildBody( \rsCore\Container $Container ) {
		$Container->addAttribute( 'class', 'splitView' );
		$this->buildTabBar( $Container );
		$this->buildTabViews( $Container );
	}


	/** Baut die den Tabs zugehörigen Views
	 * @param \rsCore\Container $Container
	 */
	public function buildTabViews( \rsCore\Container $Container ) {
		$Container = $Container->subordinate( 'div.headered.tab-content' );
		$this->buildUserView( $Container->subordinate( 'div.tab-pane.active#userView' ) );
		$this->buildGroupView( $Container->subordinate( 'div.tab-pane#groupView' ) );
		$this->buildRightsView( $Container->subordinate( 'div.tab-pane#rightsView' ) );
	}


	/** Baut die Tabbar zusammen
	 * @param \rsCore\Container $Container
	 */
	public function buildTabBar( \rsCore\Container $Container ) {
		$tabAttr = array('role' => 'tab', 'data-toggle' => 'tab');
		$userAttr = array_merge( $tabAttr, array('data-target' => '#userView') );
		$groupAttr = array_merge( $tabAttr, array('data-target' => '#groupView') );
		$rightsAttr = array_merge( $tabAttr, array('data-target' => '#rightsView') );
		$Bar = $Container->subordinate( 'header > ul.nav.nav-tabs' );
		if( self::may('editUser') )
			$Bar->subordinate( 'li > a', $userAttr, self::t("Users") );
		if( self::may('editGroup') )
			$Bar->subordinate( 'li > a', $groupAttr, self::t("Groups") );
	#	$Bar->subordinate( 'li > a', $rightsAttr, self::t("Rights") );
	}


	/** Baut eine Collapsible Section
	 * @param \rsCore\Container $Container
	 * @param string $title
	 * @return \rsCore\Container
	 */
	protected static function buildCollapsibleSection( \rsCore\Container $Container, $title ) {
		return \Brainstage\Templates\Base::buildCollapsibleSection( $Container, $title );
	}


	/** Baut die UserView
	 * @param \rsCore\Container $Container
	 */
	public function buildUserView( \rsCore\Container $Container ) {
		$ModalSpace = $Container->subordinate( 'div.modal-space' );
		$Container = $Container->subordinate( 'div.row' );
		$ListColumn = $Container->subordinate( 'div.col-md-5.list' );
		$DetailColumn = $Container->subordinate( 'div.col-md-7.details' );

		$Table = $ListColumn->subordinate( 'table#usersTable.table table-hover table-striped' );
		$Row = $Table->subordinate( 'thead > tr' );
		$Row->subordinate( 'th', self::t("Name") );
		$Row->subordinate( 'th', self::t("Email") );
	#	$Row->subordinate( 'th', self::t("Last login") );
		$TableBody = $Table->subordinate( 'tbody' );

		if( self::may('createUser') )
			$ListColumn->subordinate( 'input(button).btn.btn-default.newUser', array('data-toggle' => 'modal', 'data-target' => '#userCreationModal', 'value' => self::t("New user")) );

		$this->buildUserDetailsView( $DetailColumn );
		if( self::may('createUser') )
			$this->buildUserCreationModal( $ModalSpace );
	}


	/** Baut die Detailansicht eines Nutzers
	 * @param \rsCore\Container $Container
	 */
	public function buildUserDetailsView( \rsCore\Container $Container ) {
		$DetailsView = $Container->subordinate( 'form', array('action' => 'save') );
		$DetailsView->subordinate( 'input(hidden):id' );

		$Title = $DetailsView->subordinate( 'div.title' );
		$Title->subordinate( 'h1', self::t("Details") );
		if( self::may('editUser') )
			$Title->subordinate( 'button(button).btn.btn-primary.saveDetails', self::t("Save") );

		$Table = $DetailsView->subordinate( 'table.table.table-striped.has-textfields' );
		$TableBody = $Table->subordinate( 'tbody' );
		$Row = $TableBody->subordinate( 'tr' );
		$Row->subordinate( 'th', self::t("Name") );
		$Row->subordinate( 'td > input(text).form-control:name', array('placeholder' => self::t("Name")) );

		$Row = $TableBody->subordinate( 'tr' );
		$Row->subordinate( 'th', self::t("Email") );
		$Row->subordinate( 'td > input(text).form-control:email', array('placeholder' => self::t("Email")) );

		$Row = $TableBody->subordinate( 'tr' );
		$Row->subordinate( 'th', self::t("Last login") );
		$Row->subordinate( 'td.lastLogin' );

		$Content = self::buildCollapsibleSection( $DetailsView, self::t("Password") );
		$Content->subordinate( 'div.alert alert-info', self::t( self::PASSWORD_POLICY_DESCRIPTION ) );
		$Content->subordinate( 'p', self::t("Leave blank to leave password unchanged.") );
		$Table = $Content->subordinate( 'table.table.table-striped.userPasswordForm' );
		$TableBody = $Table->subordinate( 'tbody' );
		$Row = $TableBody->subordinate( 'tr' );
		$Row->subordinate( 'td > div.form-group has-feedback' )
			->subordinate( 'input(password).form-control:password', array('placeholder' => self::t("Password"), 'pattern' => self::PASSWORD_POLICY_REGEX) )
			->parentSubordinate( 'span.glyphicon glyphicon-ok form-control-feedback' );
		$Row->subordinate( 'td > div.form-group has-feedback' )
			->subordinate( 'input(password).form-control:password2', array('placeholder' => self::t("Password"), 'pattern' => self::PASSWORD_POLICY_REGEX) )
			->parentSubordinate( 'span.glyphicon glyphicon-ok form-control-feedback' );

		$Content = self::buildCollapsibleSection( $DetailsView, self::t("Groups") );
		$Table = $Content->subordinate( 'table.table.table-striped.userGroupsTable' );
		$TableBody = $Table->subordinate( 'tbody' );


		$Content = self::buildCollapsibleSection( $DetailsView, self::t("Rights") );
		$Table = $Content->subordinate( 'table.table.table-striped.userRightsTable' );
		$TableBody = $Table->subordinate( 'tbody' );

		$Row = $DetailsView->subordinate( 'div.row' );
		if( self::may('deleteUser') )
			$Row->subordinate( 'div.col-md-6 > button(button).btn.btn-default.removeUser', self::t("Delete") );
		if( self::may('editUser') )
			$Row->subordinate( 'div.col-md-6 > button(button).btn.btn-primary.saveDetails', self::t("Save") );
	}


	/** Baut das Modal des Nutzer-Anlege-Formulars
	 * @param \rsCore\Container $Container
	 */
	public function buildUserCreationModal( \rsCore\Container $Container ) {
		$Modal = $Container->subordinate( 'div.modal.fade#userCreationModal' )
			->subordinate( 'form', array('action' => 'create') )
			->subordinate( 'div.modal-dialog > div.modal-content' );
		$ModalHeader = $Modal->subordinate( 'div.modal-header' );
		$ModalHeader->subordinate( 'button(button).close', array('data-dismiss' => 'modal') )
			->subordinate( 'span', '&times;' );
		$ModalHeader->subordinate( 'h1.modal-title', self::t("New user") );
		$ModalBody = $Modal->subordinate( 'div.modal-body' );
		$ModalFooter = $Modal->subordinate( 'div.modal-footer' );
		$ModalFooter->subordinate( 'button(button).btn.btn-default', array('data-dismiss' => 'modal'), self::t("Cancel") );
		$ModalFooter->subordinate( 'button(button).btn.btn-primary.saveNewUser', self::t("Save") );
/*
          <div class="form-group">
            <label for="recipient-name" class="control-label">Recipient:</label>
            <input type="text" class="form-control" id="recipient-name">
          </div>
          <div class="form-group">
            <label for="message-text" class="control-label">Message:</label>
            <textarea class="form-control" id="message-text"></textarea>
          </div>
*/

		$ModalBody->subordinate( 'p > input(text).form-control:name', array('placeholder' => self::t("Name")) );
		$ModalBody->subordinate( 'p > input(text).form-control:email', array('placeholder' => self::t("Email")) );

		$ModalBody->subordinate( 'h2', self::t("Password") );
		$Columns = $ModalBody->subordinate( 'div.row' );
		$Columns->subordinate( 'div.col-md-6 > div.form-group.has-feedback' )
			->subordinate( 'input(password).form-control:password', array('pattern' => self::PASSWORD_POLICY_REGEX, 'autocomplete' => 'off', 'placeholder' => self::t("Password")) )
			->parentSubordinate( 'span.glyphicon glyphicon-ok form-control-feedback' );
		$Columns->subordinate( 'div.col-md-6 > div.form-group.has-feedback' )
			->subordinate( 'input(password).form-control:password2', array('pattern' => self::PASSWORD_POLICY_REGEX, 'autocomplete' => 'off', 'placeholder' => self::t("Password")) )
			->parentSubordinate( 'span.glyphicon glyphicon-ok form-control-feedback' );
		$ModalBody->subordinate( 'div.alert alert-info', self::t( self::PASSWORD_POLICY_DESCRIPTION ) );
	}


	/** Baut die GroupView
	 * @param \rsCore\Container $Container
	 */
	public function buildGroupView( \rsCore\Container $Container ) {
		$ModalSpace = $Container->subordinate( 'div.modal-space' );
		$Container = $Container->subordinate( 'div.row' );
		$ListColumn = $Container->subordinate( 'div.col-md-5.list' );
		$DetailColumn = $Container->subordinate( 'div.col-md-7.details' );

		$Table = $ListColumn->subordinate( 'table#groupsTable.table table-hover table-striped' );
		$Row = $Table->subordinate( 'thead > tr' );
		$Row->subordinate( 'th', self::t("Name") );
		$Row->subordinate( 'th', self::t("Members") );
		$TableBody = $Table->subordinate( 'tbody' );

		if( self::may('createGroup') )
			$ListColumn->subordinate( 'input(button).btn.btn-default.newGroup', array('data-toggle' => 'modal', 'data-target' => '#groupCreationModal', 'value' => self::t("New group")) );

		$this->buildGroupDetailsView( $DetailColumn );
		if( self::may('createGroup') )
			$this->buildGroupCreationModal( $ModalSpace );
	}


	/** Baut die Detailansicht einer Gruppe
	 * @param \rsCore\Container $Container
	 */
	public function buildGroupDetailsView( \rsCore\Container $Container ) {
		$DetailsView = $Container->subordinate( 'form', array('action' => 'groups-save') );
		$DetailsView->subordinate( 'input(hidden):id' );

		$Title = $DetailsView->subordinate( 'div.title' );
		$Title->subordinate( 'h1', self::t("Details") );
		if( self::may('editGroup') )
			$Title->subordinate( 'button(button).btn.btn-primary.saveDetails', self::t("Save") );

		$Table = $DetailsView->subordinate( 'table.table.table-striped.has-textfields' );
		$TableBody = $Table->subordinate( 'tbody' );
		$Row = $TableBody->subordinate( 'tr' );
		$Row->subordinate( 'th', self::t("Name") );
		$Row->subordinate( 'td > input(text).form-control:name', array('placeholder' => self::t("Name")) );

		$Content = self::buildCollapsibleSection( $DetailsView, self::t("Rights") );
		$Table = $Content->subordinate( 'table.table.table-striped.groupRightsTable > tbody' );

		$Content = self::buildCollapsibleSection( $DetailsView, self::t("Members") );
		$Table = $Content->subordinate( 'table.table.table-striped.groupMembersTable > tbody' );

		$Row = $DetailsView->subordinate( 'div.row' );
		if( self::may('deleteGroup') )
			$Row->subordinate( 'div.col-md-6 > button(button).btn.btn-default.removeGroup', self::t("Delete") );
		if( self::may('editGroup') )
			$Row->subordinate( 'div.col-md-6 > button(button).btn.btn-primary.saveDetails', self::t("Save") );
	}


	/** Baut das Modal des Gruppen-Anlege-Formulars
	 * @param \rsCore\Container $Container
	 */
	public function buildGroupCreationModal( \rsCore\Container $Container ) {
		$Modal = $Container->subordinate( 'div.modal.fade#groupCreationModal' )
			->subordinate( 'form', array('action' => 'groups-create') )
			->subordinate( 'div.modal-dialog > div.modal-content' );
		$ModalHeader = $Modal->subordinate( 'div.modal-header' );
		$ModalHeader->subordinate( 'button(button).close', array('data-dismiss' => 'modal') )
			->subordinate( 'span', '&times;' );
		$ModalHeader->subordinate( 'h1.modal-title', self::t("New group") );
		$ModalBody = $Modal->subordinate( 'div.modal-body' );
		$ModalFooter = $Modal->subordinate( 'div.modal-footer' );
		$ModalFooter->subordinate( 'button(button).btn.btn-default', array('data-dismiss' => 'modal'), self::t("Cancel") );
		$ModalFooter->subordinate( 'button(button).btn.btn-primary.saveNewGroup', self::t("Save") );

		$ModalBody->subordinate( 'p > input(text).form-control:name', array('placeholder' => self::t("Name")) );
	}


	/** Baut die GroupView
	 * @param \rsCore\Container $Container
	 */
	public function buildRightsView( \rsCore\Container $Container ) {
		$ModalSpace = $Container->subordinate( 'div.modal-space' );
		$Container = $Container->subordinate( 'div.row' );
		$ListColumn = $Container->subordinate( 'div.col-md-12.list' );
	#	$DetailColumn = $Container->subordinate( 'div.col-md-7.details' );

		$Table = $ListColumn->subordinate( 'table#rightsTable.table table-hover table-striped' );
		$Row = $Table->subordinate( 'thead > tr' );
		$Row->subordinate( 'th', "Plugin" );
		$Row->subordinate( 'th', self::t("Rights") );
		$TableBody = $Table->subordinate( 'tbody' );

		foreach( \Brainstage\Brainstage::getPluginPrivileges() as $pluginName => $privileges ) {
			$pluginName = ltrim( $pluginName, '\\' );
			$pluginName = str_replace( '\\', '/', $pluginName );
			$Row = $TableBody->subordinate( 'tr' );
			$Row->subordinate( 'td', $pluginName );
			$Row->subordinate( 'td', is_array( $privileges ) ? implode( ', ', array_keys( $privileges ) ) : '' );
		}
	}


/* API Plugin */

	/** Listet alle Nutzer auf
	 * @return array
	 */
	public function api_listUsers( $params ) {
		self::throwExceptionIfNotAuthorized();
		$start = valueByKey( $params, 'start', 0 );
		$limit = self::DEFAULT_INTERVAL_SIZE; // valueByKey( $params, 'limit', self::DEFAULT_INTERVAL_SIZE );
		$users = array();
		foreach( $this->getUsers( $start*$limit, $limit ) as $User ) {
			$columns = $User->getColumns();
			unset( $columns['password'] );
			if( $User->lastLogin )
				$columns['lastLogin'] = $User->lastLogin->format( self::t('Y-m-d H:i:s', 'Date and Time: full year, hours, minutes and seconds') );
			$columns['groups'] = array();
			foreach( $User->getGroups() as $Group )
				$columns['groups'][] = $Group->getPrimaryKeyValue();
			$columns['rights'] = array();
			foreach( $User->getRights() as $Right )
				$columns['rights'][ $Right->key ] = $Right->value;
			$users[ $User->getPrimaryKeyValue() ] = $columns;
		}
		return $users;
	}


	/** Speichert die Nutzerdaten
	 * @return array
	 */
	public function api_saveUser( $params ) {
		self::throwExceptionIfNotPrivileged( 'editUser' );
		$userId = postVar( 'id', null );
		$User = \Brainstage\User::getUserById( $userId );

		$fields = array('name', 'email');
		foreach( $fields as $field ) {
			if( isset( $_POST[ $field ] ) ) {
				$value = postVar( $field );
				$User->set( $field, $value );
			}
		}

		$password = postVar('password');
		if( $password === postVar('password2', true) && preg_match( '/'. self::PASSWORD_POLICY_REGEX .'/', $password ) )
			$User->password = $password;
		$User->adopt();

		$groups = array();
		foreach( $User->getGroups() as $Group ) {
			$groups[ $Group->getPrimaryKeyValue() ] = valueByKey( $_POST['groups'], $Group->getPrimaryKeyValue(), 'off' );
		}
		foreach( postVar( 'groups', array() ) as $groupId => $selected ) {
			$groups[ $groupId ] = $selected;
		}
		foreach( $groups as $groupId => $selected ) {
			$Group = \Brainstage\Group::getGroupById( $groupId );
			$UserGroupRelation = \Brainstage\UserGroup::getRelation( $User, $Group );
			if( $selected == 'off' ) {
				if( $UserGroupRelation )
					$UserGroupRelation->remove();
			}
			elseif( $selected == 'on' ) {
				if( !$UserGroupRelation )
					\Brainstage\UserGroup::addRelation( $User, $Group );
			}
		}

		$userEqualsSessionUser = ( $User->getPrimaryKeyValue() == user()->getPrimaryKeyValue() );
		if( user()->isSuperAdmin() || !$userEqualsSessionUser ) {
			$rights = postVar( 'rights', array() );
			foreach( self::getRights() as $right => $type ) {
					$selected = valueByKey( $rights, $right, 'off' );
					$UserRight = \Brainstage\UserRight::getRightByKey( $right, $User );
					if( $UserRight && $selected == 'off' ) {
						if( $right != 'Brainstage:superadmin' || !$userEqualsSessionUser )
							$UserRight->remove();
					}
					elseif( $selected == 'on' ) {
						$rightValue = valueByKey( postVar( 'rightValues', array() ), $right, 1 );
						if( $UserRight )
							$UserRight->value = $rightValue;
						else
							\Brainstage\UserRight::addRight( $right, $rightValue, $User, false );
					}
			}
		}

		$columns = $User->getColumns();
		unset( $columns['password'] );
		return $columns;
	}


	/** Erstellt einen neuen Nutzer
	 * @return array
	 */
	public function api_createUser( $params ) {
		self::throwExceptionIfNotPrivileged( 'createUser' );

		$email = postVar( 'email', null );
		$User = \Brainstage\User::getUserByEmail( $email );
		if( $User )
			throw new \Exception( "This email address is already linked with another account." );
		if( postVar('password', false) !== postVar('password2', true) )
			throw new \Exception( "Passwords do not match." );
		if( !preg_match( '/'. self::PASSWORD_POLICY_REGEX .'/', postVar('password') ) )
			throw new \Exception( "Your password does not comply with the password policy." );

		$User = \Brainstage\User::addUser( $email, postVar('password'), true );
		$User->name = postVar( 'name' );
		$User->adopt();

		$columns = $User->getColumns();
		unset( $columns['password'] );
		return $columns;
	}


	/** Löscht einen Benutzer
	 * @return array
	 */
	public function api_deleteUser( $params ) {
		self::throwExceptionIfNotPrivileged( 'deleteUser' );

		$userId = postVar( 'id', null );
		$User = \Brainstage\User::getUserById( $userId );
		if( !$User )
			throw new \Exception( "This user does not exist." );

		return $User->remove();
	}


	/** Listet alle Gruppen auf
	 * @return array
	 */
	public function api_listGroups( $params ) {
		self::throwExceptionIfNotAuthorized();
		$start = valueByKey( $params, 'start', 0 );
		$limit = self::DEFAULT_INTERVAL_SIZE; // valueByKey( $params, 'limit', self::DEFAULT_INTERVAL_SIZE );
		$groups = array();
		foreach( $this->getGroups( $start*$limit, $limit ) as $Group ) {
			$columns = $Group->getColumns();
			$columns['memberCount'] = strval( count( $Group->getMembers() ) );
			$columns['members'] = array();
			foreach( $Group->getMembers() as $Member )
				$columns['members'][] = intval( $Member->getPrimaryKeyValue() );
			$columns['rights'] = array();
			foreach( $Group->getRights() as $Right )
				$columns['rights'][ $Right->key ] = $Right->value;
			$groups[ $Group->getPrimaryKeyValue() ] = $columns;
		}
		return $groups;
	}


	/** Speichert die Nutzerdaten
	 * @return array
	 */
	public function api_saveGroup( $params ) {
		self::throwExceptionIfNotPrivileged( 'editGroup' );
		$groupId = postVar( 'id', null );
		$Group = \Brainstage\Group::getGroupById( $groupId );

		$fields = array('name');
		foreach( $fields as $field ) {
			if( isset( $_POST[ $field ] ) ) {
				$value = postVar( $field );
				$Group->set( $field, $value );
			}
		}
		$Group->adopt();

		$rights = postVar( 'rights', array() );
		foreach( self::getRights() as $right => $type ) {
			$selected = valueByKey( $rights, $right, 'off' );
			$GroupRight = \Brainstage\GroupRight::getRightByKey( $right, $Group );
			if( $GroupRight && $selected == 'off' ) {
				$GroupRight->remove();
			}
			elseif( $selected == 'on' ) {
				$rightValue = valueByKey( postVar( 'rightValues', array() ), $right, 1 );
				if( $GroupRight )
					$GroupRight->value = $rightValue;
				else
					\Brainstage\GroupRight::addRight( $right, $rightValue, $Group, false );
			}
		}

		return $Group->getColumns();
	}


	/** Erstellt eine neue Gruppe
	 * @return array
	 */
	public function api_createGroup( $params ) {
		self::throwExceptionIfNotPrivileged( 'createGroup' );

		$name = postVar( 'name', null );
		$Group = \Brainstage\Group::getGroupByName( $name );
		if( $Group )
			throw new \Exception( "This name is already used by another group." );

		$Group = \Brainstage\Group::addGroup( $name, true );
		$Group->adopt();

		$columns = $Group->getColumns();
		return $columns;
	}


	/** Löscht eine Gruppe
	 * @return array
	 */
	public function api_deleteGroup( $params ) {
		self::throwExceptionIfNotPrivileged( 'deleteGroup' );

		$groupId = postVar( 'id', null );
		$Group = \Brainstage\Group::getGroupById( $groupId );
		if( !$Group )
			throw new \Exception( "This group does not exist." );

		return $Group->remove();
	}


	/** Listet alle Rechtebezeichner auf
	 * @return array
	 */
	public function api_listRights( $params ) {
		self::throwExceptionIfNotAuthorized();
		return self::getRights( true );
	}


}