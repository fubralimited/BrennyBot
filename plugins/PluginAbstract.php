<?php

 /**
  * The base class from which plugins should be extended.
	* See ExamplePlugin.php for details on how to use this class.
  */
abstract class PluginAbstract {

 /**
  * Contains a reference to the main bot instance to enable the plugin to
  * interact with the bot's public methods / plugin API.
	*/
	protected $_controller;

 /**
  * Constructor. Assigns the controller reference to the instance variable.
  *
  * @param $controller BrennyBot Reference to the controlling instance.
  */
	public function __construct(&$controller) {

		$this->_controller = $controller;

	}

}