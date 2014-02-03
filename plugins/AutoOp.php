<?php

class AutoOp extends PluginAbstract {

	protected $_rules = array();

	function __construct($config, &$controller) {

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