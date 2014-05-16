<?php

 /**
  * Logs activity in channels.
  */
class ChannelLogger extends PluginAbstract {

	protected $_logDir = null;
	protected $_channels = array();
	
	protected $_channelUsers = array();

 /**
  * Constructor. Sets up logging directory, log array and hooks commands to
  * help system.
  *
  * @param array $config Configuration details in array.
  * @param BrennyBot $controller Reference to the controlling instance.
  */
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
		foreach ($config['channels'] AS $channel) {
			$this->_channels[] = $this->_controller->add_channel_hash($channel);
		}
		$this->_controller->add_bot_channel($config['channels']);
		
	 // Register the !startlog and !stoplog commands...
		$this->_controller->add_hooked_command('!startlog <#channel>', 'Makes the bot join the specified channel and start logging.');
		$this->_controller->add_hooked_command('!stoplog <#channel>', 'Makes the bot stop logging and leave the specified channel.');

	}
	
 /**
  * De-registers the commands in the help system.
  */
	function _destruct() {
	
	 // Deregister the !startlog and !stoplog commands...
		$this->_controller->remove_hooked_command('!startlog <#channel>');
		$this->_controller->remove_hooked_command('!stoplog <#channel>');
	
	}
	
 /**
  * Called when the bot joins a new channel. Adds an empy element to
  * $this->_channelUsers to take the list of users in the channel.
  *
  * @param string $channelName Name of channel joined.
  */
	public function join_channel($channelName) {

		$this->_channelUsers[$channelName] = array();

	}
	
 /**
  * Called when a message is sent directly to the bot. Responds to both
  * !startlog and !stoplog commands. If the bot is not in the channel given with
  * the !startlog command it joins it. Parts the given channel and stops logging
  * when sent a !stoplog command.
  *
  * @param array $fromDetails Details of the user the message comes from. See
  *                           BrennyBot::parse_user_ident() for details.
  * @param string $message The actual message.
  */
	public function private_message(array $fromDetails, $message) {
	
		if (preg_match('/^!startlog (#.*)$/', $message, $channelMatches)) {
			$this->_channels[] = $channelMatches[1];
			$this->_controller->add_bot_channel($channelMatches[1]);
		} else if (preg_match('/^!stoplog (#.*)$/', $message, $channelMatches)) {
			if ($channelKey = array_search($channelMatches[1], $this->_channels)) {
				$this->_controller->remove_bot_channel($channelMatches[1]);
				unset($this->_channels[$channelKey]);
			}
		}
	
	}

 /**
  * Called when a message is posted to a channel that the bot is in. Logs all
  * messages sent to this method.
  *
  * @param array $fromDetails Details of the user the message comes from. See
  *                           BrennyBot::parse_user_ident() for details.
  * @param string $channelName Channel the message was posted in.
  * @param string $message The actual message.
  */
	public function channel_message(array $fromDetails, $channelName, $message) {
	
		if (substr($message, 1, 6) == 'ACTION') {
			$this->_write_log($channelName, '* '.$fromDetails[1].' '.trim(substr($message, 8)));
		} else {
			$this->_write_log($channelName, $fromDetails[1].': '.trim($message));
		}

	}
	
 /**
  * Called when a message is sent from the bot to the server. If the outgoing
  * message is in channel format, the message is logged.
  *
  * @param string $message The full message.
  */
	public function send_message($message) {

		if (preg_match('/^PRIVMSG (#.+?) :(.+)$/', $message, $matches)) {
			$this->_write_log($matches[1], $this->_controller->get_nickname().': '.$matches[2]);
		}
	
	}

 /**
  * Called when a system message (other than PING and ERROR) is received. If the
  * message is broadly user related (JOIN, PART, etc.) it will get logged.
  *
  * @param string $message The full message.
  */
	public function data_message($message) {

		if (isset($message[1])) {
			if ($this->_controller->get_nickname() != $message[2]) {
				if (':#' == substr($message[2], 0, 2)) {
					$channelName = trim(substr($message[2], 1));
				} else {
					$channelName = trim($message[2]);
				}
			}
			$fromDetails = $this->_controller->parse_user_ident($message[0]);

			switch ($message[1]) {
			
				// Channel related notices...
			  case 'JOIN':
					$this->_write_log($channelName, '['.$fromDetails[1].' joined chat]');
					$this->_add_nick_to_channel($channelName, $fromDetails[1]);
				break;
				case 'PART':
					$this->_write_log($channelName, '['.$fromDetails[1].' left chat]');
					$this->_remove_nick_from_channel($channelName, $fromDetails[1]);
				break;
				case 'QUIT':
					foreach ($this->_channelUsers AS $channelName => $channelUserList) {
						if (in_array($fromDetails[1], $channelUserList)) {
							$this->_write_log($channelName, '['.$fromDetails[1].' quit IRC: '.substr(implode(' ', array_slice($message, 2)), 1).']');
							$this->_remove_nick_from_channel($channelName, $fromDetails[1]);
						}
					}
				break;
				case 'KICK':
					$kickMessage = substr(implode(' ', array_slice($message, 4)), 1);
					$this->_write_log($channelName, '['.$fromDetails[1].' kicked '.$message[3].': \''.$kickMessage.'\']');
					$this->_remove_nick_from_channel($channelName, $fromDetails[1]);
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
				case 'TOPIC':
					$newTopic = substr(implode(' ', array_slice($message, 3)), 1);
					$this->_write_log($message[2], '['.$fromDetails[1].' set the topic to: '.$newTopic.']');
				break;
				
				// Initial channel join user list...
				case '353':
					$this->_channelUsers[$message[4]] = array();
					$message[5] = substr($message[5], 1);
					foreach (array_slice($message, 5) AS $channelUser) {
						if ('@' == substr($channelUser, 0, 1)) {
							$this->_add_nick_to_channel($message[4], substr($channelUser, 1));
						} else {
							$this->_add_nick_to_channel($message[4], $channelUser);
						}
					}
				break;
				
			}
		}

	}
	
 /**
  * Writes a line to the appropriate log file only if it relates to a channel
  * which is supposed to be logged. Note: returns false if the channel is not
  * supposed to be logged as well as if there was an error writing the log line.
  *
  * @param string $channel Channel the log line relates to.
  * @param string $message Message to log.
  * @return boolean True if the line was written, false if it was not for some reason.
  */
	protected function _write_log($channel, $message) {
	
		if (in_array($channel, $this->_channels)) {
			$logDirectory = $this->_logDir.$channel;
			if (!file_exists($logDirectory)) {
				mkdir($logDirectory);
			}
			if (false !== file_put_contents($logDirectory.'/'.date('Ymd').'.log', '['.date('H:i:s').'] '.$message.PHP_EOL, FILE_APPEND)) {
				return true;
			}
		}

		return false;
	
	}
	
 /**
  * Removes a nick from the internal channel user list.
  *
  * @param string $channel Channel the user is leaving.
  * @param string $nickname Nickname to remove.
  * @return boolean True if user was in the given channel and has been removed, false if not.
  */
	protected function _remove_nick_from_channel($channelName, $nickname) {
	
		if (false !== ($channelUserKey = array_search($nickname, $this->_channelUsers[$channelName]))) {
			unset($this->_channelUsers[$channelName][$channelUserKey]);
			return true;
		}
		
		return false;
	
	}
	
 /**
  * Adds a nick to the internal channel user list.
  *
  * @param string $channel Channel the user is joining.
  * @param string $nickname Nickname to add.
  * @return boolean True if user was not already in the given channel and has been added, false if not.
  */
	protected function _add_nick_to_channel($channelName, $nickname) {
	
		if (!in_array($nickname, $this->_channelUsers[$channelName])) {
			$this->_channelUsers[$channelName][] = $nickname;
			return true;
		}
		
		return false;
	
	}

}