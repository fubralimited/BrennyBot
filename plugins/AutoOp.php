<?php

 /**
  * Automatically gives operator privilidges to specified people when they join
  * the given channels.
  */
class AutoOp extends PluginAbstract {

 /**
  * Array of rules to obey:
	*   key => channel name
	*   value => array of users to auto-op in the [key] channel
  */
	protected $_rules = array();

 /**
  * Constructor. Tidies up and loads the given config array.
  *
  * @see AutoOp::_rules()
  * @see PluginAbstract::__construct()
  * @param array $config Configuration details in array.
  * @param BrennyBot $controller Reference to the controlling instance.
  */
	function __construct(array $config, &$controller) {

	 // Call parent constructor...
		parent::__construct($controller);
		
	 // Do a little tidying on the configuration array if required...
		foreach ($config AS $channel => $users) {
			$this->_controller->add_bot_channel($channel);
			$channel = $this->_controller->add_channel_hash($channel);
			foreach ($users AS $user) {
				$this->_rules[$channel][] = strtolower($user);
			}
		}

	}

 /**
  * Handles data messages and acts on channel joins: if, according to the
  * configuration, the user should be auto-opped then carries out the opping.
  *
  * @see AutoOp::_rules()
  * @param string $message The data message to handle.
  */
	public function data_message($message) {

		if (isset($message[1]) && ('JOIN' == $message[1])) {
		
			$channelName = trim(substr($message[2], 1));
			list(, $nickname) = $this->_controller->parse_user_ident($message[0]);

			if (isset($this->_rules[$channelName]) && in_array(strtolower($nickname), $this->_rules[$channelName])) {
				$this->_controller->send_data('MODE '.$channelName.' +o '.$nickname);
			}

		}

	}

}