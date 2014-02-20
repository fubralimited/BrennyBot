<?php

 /**
  * Contains a set of commands to control the bot, as well as get data and
  * statistics about the current instance of the bot.
  */
class BotControl extends PluginAbstract {


 /**
  * Constructor. Called when the plugin is first loaded by the bot. Activity
  * required before the plugin receives any information from the IRC server
  * should be done here. Should call super($controller).
  *
  * @param $config mixed The configuration element matching the plugin name will
  *                      be passed here. If there isn't one then null will be
  *                      passed.
  * @param $controller BrennyBot Reference to the controlling instance.
  */
	function __construct($config, &$controller) {

		parent::__construct($controller);

		$this->_controller->add_hooked_command('!memory', 'Reports the current and peak memory usage of the bot.');
		$this->_controller->add_hooked_command('!uptime', 'Reports the current uptime of the bot.');

	}

 /**
  * Destructor. Called when the plugin is shut down by the bot; usually because
  * the bot is going offline or restarting, but possibly because the plugins are
  * being reloaded. Tidy up, clear locks, disconnect from APIs and databases
  * here. Do NOT unset the _controller instance variable.
  */
	function __destruct() {
	
		$this->_controller->remove_hooked_command(array('!memory', '!uptime'));

	}

 /**
  * Called when a message is posted to a channel that the bot is in.
  *
  * @param $fromDetails array Details of the user the message comes from. See
  *                           BrennyBot::parse_user_ident() for details.
  * @param $channelName string Channel the message was posted in.
  * @param $message string The actual message.
  */
	public function channel_message(array $fromDetails, $channelName, $message) {
	
		if (is_array($response = $this->_message_response($message))) {
			foreach ($response AS $responseMessage) {
				$this->_controller->send_data('PRIVMSG '.$channelName.' :'.$responseMessage);
			}
		}

	}

 /**
  * Called when a message is sent directly to the bot.
  *
  * @param $fromDetails array Details of the user the message comes from. See
  *                           BrennyBot::parse_user_ident() for details.
  * @param $message string The actual message.
  */
	public function private_message(array $fromDetails, $message) {
	
		if (is_array($response = $this->_message_response($message))) {
			foreach ($response AS $responseMessage) {
				$this->_controller->send_data('PRIVMSG '.$fromDetails[1].' :'.$responseMessage);
			}
		}

	}
	
 /**
  * Called when a system message (other than PING and ERROR) is received.
  *
  * @param $message string The full message.
  */
	public function data_message($message) {

		if (isset($message[1]) && ($message[1] == 'INVITE')) {
			$channel = substr($message[3], 1);
			$this->_controller->add_bot_channel($channel);
		}
	
	}
	
	protected function _message_response($message) {

		$response = array();
		switch ($message) {
			case '!memory':
				$unit = array('b','kb','mb','gb','tb','pb');
				$current = memory_get_usage();
				$response[] = 'Current memory use: '.(@round($current / pow(1024, ($i = floor(log($current, 1024)))), 2).' '.$unit[$i]);
				$peak = memory_get_peak_usage();
				$response[] = 'Peak memory use: '.(@round($peak / pow(1024, ($i = floor(log($peak, 1024)))), 2).' '.$unit[$i]);
			break;
			case '!uptime':
				$uptimeSeconds = time() - $this->_controller->startupTime;
				$days = floor($uptimeSeconds / 86400);
				$uptimeSeconds = $uptimeSeconds - ($days * 86400);
				$response[] = 'Uptime: '.number_format($days).' days '.gmdate('G \h\o\u\r\s i \m\i\n\u\t\e\s s \s\e\c\o\n\d\s', $uptimeSeconds).'.';
			break;
			default:
				return true;
		}

		return $response;

	}

}