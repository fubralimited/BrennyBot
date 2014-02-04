<?php

class ChannelLogger extends PluginAbstract {

	protected $_logDir;
	protected $_channels;

	function __construct($config, &$controller) {

	 // Call parent constructor...
		parent::__construct($controller);
		
	 // Set the log directory...
		if (isset($config['directory'])) {
			if ('/' == substr($config['directory'], -1)) {
				$this->_logDir = $config['directory'];
			} else {
				$this->_logDir = $config['directory'].'/';
			}
		} else {
			$this->_logDir = 'logs/';
		}
		
	 // Make the bot join all the channels we're going to log...
		$this->_controller->add_bot_channel($config['channels']);

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
				case 'KICK':
					$kickMessage = trim(substr(implode(' ', array_slice($message, 4)), 1));
					$this->_write_log($channelName, '['.$fromDetails[1].' kicked '.$message[3].': \''.$kickMessage.'\']');
				break;
				case 'MODE':
					if ('+o' == $message[3]) {
						$this->_write_log($message[2], '['.$fromDetails[1].' gave channel operator status to '.trim($message[4]).']');
					} else if ('-o' == $message[3]) {
						$this->_write_log($message[2], '['.$fromDetails[1].' removed channel operator status from '.trim($message[4]).']');
					} else if ('+v' == $message[3]) {
						$this->_write_log($message[2], '['.$fromDetails[1].' gave voice to '.trim($message[4]).']');
					} else if ('-v' == $message[3]) {
						$this->_write_log($message[2], '['.$fromDetails[1].' removed voice from '.trim($message[4]).']');
					}
				break;
			}
		}

	}
	
	protected function _write_log($channel, $message) {
	
		$logDirectory = $this->_logDir.$channel;
		if (!file_exists($logDirectory)) {
			mkdir($logDirectory);
		}
		
		if (false !== file_put_contents($logDirectory.'/'.date('Ymd').'.log', '['.date('H:i:s').'] '.$message.PHP_EOL, FILE_APPEND)) {
			return true;
		}

		return false;
	
	}

}