<?php

 /**
  * Adds a command to output all currently registered plugin commands.
  */
class Help extends PluginAbstract {

 /**
  * Sets up the _controller instance variable and registers the !help command.
  */
	function __construct($config, &$controller) {

		parent::__construct($controller);

		$this->_controller->add_hooked_command('!help', 'Returns this help information.', 'help');
		$this->_controller->add_hooked_command('!help [pluginname]', 'Returns help information for a currently loaded plugin.', 'help');

	}

 /**
  * De-registers the !help command.
  */
	function __destruct() {

		$this->_controller->remove_hooked_command('!help');
		$this->_controller->remove_hooked_command('!help [pluginname]');

	}

 /**
  * Handles the reciept of a !help message in channels.
  */
	public function channel_message(array $fromDetails, $channelName, $message) {

		if ('!help' == substr($message, 0, 5)) {
			if (is_array($response = $this->_help_response($message))) {
				foreach ($response AS $responseMessage) {
					$this->_controller->send_data('PRIVMSG '.$channelName.' :'.$responseMessage);
				}
			}
		}

	}

 /**
  * Handles the reciept of a !help message in private messages.
  */
	public function private_message(array $fromDetails, $message) {

		if ('!help' == substr($message, 0, 5)) {
			if (is_array($response = $this->_help_response($message))) {
				foreach ($response AS $responseMessage) {
					$this->_controller->send_data('PRIVMSG '.$fromDetails[1].' :'.$responseMessage);
				}
			}
		}

	}

 /**
  * Constructs the response to the !help command based on data available in the
  * controller and returns it for the listeners to output.
  */
	protected function _help_response($message) {

		$message = trim($message);
		$commands = $this->_controller->hooked_commands();
		$response = array();
		
		if ('!help' == $message) {
			$response[] = 'A brief guide to the help available with '.$this->_controller->get_nickname().':';
			foreach ($commands['help'] AS $command => $description) {
				$response[] = '  '.$command.' - '.$description;
			}
			$response[] = $this->_controller->get_nickname().' has the following plugins installed:';
			foreach ($commands AS $source => $sourceCommands) {
				if (('zzz' != $source) && ('help' != $source)) {
					$response[] = '  '.$source;
				}
			}
		} else {
			if (count($commands) > 0) {
				$response[] = 'The following commands can be used with '.$this->_controller->get_nickname().':';
				foreach ($commands AS $source => $sourceCommands) {
					foreach ($sourceCommands AS $command => $description) {
						$response[] = '  '.$command.' - '.$description;
					}
				}
			} else {
				$response[] = $this->_controller->get_nickname().' does not respond to any commands.';
			}
		}
		
		return $response;

	}

}