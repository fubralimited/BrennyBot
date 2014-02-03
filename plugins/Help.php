<?php

class Help extends PluginAbstract {

	function __construct($config, &$controller) {

		parent::__construct($controller);

		$this->_controller->add_hooked_command('!help', 'Returns this help information.');

	}

	function __destruct() {

		$this->_controller->remove_hooked_command('!help');

	}

	public function channel_message(array $fromDetails, $channelName, $message) {

		if ('!help' == $message) {
			if (is_array($response = $this->_help_response($message))) {
				foreach ($response AS $responseMessage) {
					$this->_controller->send_data('PRIVMSG '.$channelName.' :'.$responseMessage);
				}
			}
		}

	}

	public function private_message(array $fromDetails, $message) {

		if ('!help' == $message) {
			if (is_array($response = $this->_help_response($message))) {
				foreach ($response AS $responseMessage) {
					$this->_controller->send_data('PRIVMSG '.$fromDetails[1].' :'.$responseMessage);
				}
			}
		}

	}

	protected function _help_response($message) {

		$response = array();
		$commands = $this->_controller->hooked_commands();
		if (count($commands) > 0) {
			$response[] = 'The following commands can be used with '.$this->_controller->get_nickname().':';
			foreach ($commands AS $command => $description) {
				$response[] = '  '.$command.' - '.$description;
			}
		} else {
			$response[] = $this->_controller->get_nickname().' does not respond to any commands.';
		}
		
		return $response;

	}

}