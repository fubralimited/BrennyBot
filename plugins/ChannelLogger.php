<?php

class ChannelLogger extends PluginAbstract {

	function __construct($config, &$controller) {

	 // Call parent constructor...
		super($controller);
		
	 // Make the bot join all the channels we're going to log...
		$this->_controller->add_bot_channel($config);

	}

	public function channel_message(array $fromDetails, $channelName, $message) {
	
		if (substr($message, 1, 6) == 'ACTION') {
			$this->_write_log($channelName, '* '.$fromDetails[1].' '.trim(substr($message, 8)));
		} else {
			$this->_write_log($channelName, $fromDetails[1].': '.trim($message));
		}

	}

	public function data_message($message) {

		if (isset($message[1])) {
			if (':#' == substr($message[2], 0, 2)) {
				$channelName = trim(substr($message[2], 1));
			} else {
				$channelName = trim($message[2]);
			}
			$fromDetails = $this->_controller->parse_user_ident($message[0]);

			switch ($message[1]) {
			  case 'JOIN':
					$this->_write_log($channelName, '['.$fromDetails[1].' joined chat]');
				break;
				case 'PART':
					$this->_write_log($channelName, '['.$fromDetails[1].' left chat]');
				break;
			}
		}

	}
	
	protected function _write_log($channel, $message) {
	
		file_put_contents('logs/'.$channel.'.log', '['.date('H:i:s').'] '.$message.PHP_EOL, FILE_APPEND);
	
	}

}