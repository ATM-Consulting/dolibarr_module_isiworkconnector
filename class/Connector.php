<?php




abstract class Connector extends SeedObject {


	public $Login;
	public $Password;

	/**
	 * ConnectorInterface constructor
	 *
	 */
	public function __construct(&$db){

		parent::__construct($db);

	}

	/**
	 * Function that need to be override by children
	 */
	abstract public function setCredentials($accountCredentials);

	/**
	 * Function that need to be override by children
	 */
	abstract public function sendQuery($context,$params);

	/**
	 * Function that need to be override by children
	 */
	abstract function getCustomUri($context,$params);




}
