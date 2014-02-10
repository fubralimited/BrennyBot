<?php

class BrennyBot {

 /**
  * Socket connection to IRC server.
  */
	protected $_connection;
 /**
  * Boolean flag indicating if the server has accepted and completed our log in.
  */
	protected $_loginComplete = false;
	
 /**
  * Currently loaded bot configuration.
  */
	protected $_config;
 /**
  * Array of channels the bot has been told to be in, and the current status:
  *   channelname => boolean; true if in the channel, false if not
  */
	protected $_channels = array();
 /**
  * Array of plugins currently loaded. The instances themselves.
  */
	protected $_plugins = array();
 /**
  * All the commands which the bot will respond to based on the loaded plugins.
  *   'command' => brief help description
  */
	protected $_hookedCommands = array();

 /**
  * Nickname the bot is currently using.
  */
	protected $_botNickname;
 /**
  * Username the bot is currently using.
  */
	protected $_botUsername;

 /**
  * The time, as a unix timestamp, that the bot loaded.
  */
  public $startupTime;
	
 /* Magic methods. */

 /**
  * Constructor. Sets the startup config, connects to server, logs in and starts
  * the main plugin routine.
  *
  * Configuration array must contain:
  *   server => hostname of server to connect to
  *   port => port to connect on
  *   username => name (for username@hostname in WHOIS)
  *   nickname => actual nickname the bot will use
  *
  * @param $config array Configuration array. See above.
  * @see BrennyBot::load_config()
  */
	public function __construct(array $config) {
	
		set_time_limit(0);
	
		$this->load_config($config);
		$this->startupTime = time();
		
		if (isset($this->_config['server']) && isset($this->_config['port'])) {
		  if ($this->_connect($this->_config['server'], $this->_config['port'])) {
				if ($this->_login($this->_config['username'], $this->_config['nickname'])) {
					$this->_load_plugins();
					$this->_main();
				} else {
					$this->_log('Unable to log in to server');
				}
			} else {
			  $this->_log('Unable to open socket connection to '.$this->_config['server'].':'.$this->_config['port']);
			}
		}
	
	}
	
 /**
  * Destructor. Shuts down the bot: notifies the plugins before termination.
  */
	public function __destruct() {

	 // Shutdown each of the plugins...
		foreach ($this->_plugins AS $plugin) {
			unset($plugin);
		}
	
	}
	
 /* Private methods. */
	
 /**
  * The body of the bot. Deals with PING and ERROR directly then hands parsed
  * messages off to the plugin system to do whatever else they need to do.
  */
	private function _main() {
	
		while (true) {
		
			$serverData = explode(' ', $this->_get_data());

			if (!$this->_handle_server_ping($serverData)) {
				$this->_handle_server_error($serverData);
				
	 // If log in hasn't completed then let's just wait...
				if (false === $this->_loginComplete) {
				
					if (isset($serverData[1]) && '004' == $serverData[1]) {
						$this->_loginComplete = true;
						$this->_log('Completed log in to '.$this->_config['server']);
					}
				
	 // Once log in is complete switch to normal operation...
				} else {
				
	 // Log in to channels which we're not in but should be...
					if (is_array($this->_channels)) {
						foreach ($this->_channels AS $channelName => $inChannel) {
							if (true !== $inChannel) {
								$this->_join_channel($channelName);
								$this->_channels[$channelName] = true;
								foreach ($this->_plugins AS $plugin) {
									if (method_exists($plugin, 'join_channel')) {
										$plugin->join_channel($channelName);
									}
								}
							}
						}
					}
					
	 // Pass messages on to plugins...
					if (strlen($serverData[0]) > 0) {
						if (isset($serverData[1]) && ('PRIVMSG' == $serverData[1])) {
						
	 // Parse details...
							$fromDetails = $this->parse_user_ident($serverData[0]);
							$message = trim(substr(implode(' ', array_slice($serverData, 3)), 1));

	 // Channel messages...
							if ('#' == substr($serverData[2], 0, 1)) {
								foreach ($this->_plugins AS $plugin) {
									if (method_exists($plugin, 'channel_message')) {
										$plugin->channel_message($fromDetails, $serverData[2], $message);
									}
								}
	 // Private messages...
							} else {
								foreach ($this->_plugins AS $plugin) {
									if (method_exists($plugin, 'private_message')) {
										$plugin->private_message($fromDetails, $message);
									}
								}
							}
						} else {
	 // Data / non-chat messages...
							foreach ($this->_plugins AS $plugin) {
								if (method_exists($plugin, 'data_message')) {
									$plugin->data_message($serverData);
								}
							}
						}
					}

	 // Tick the plugins...
					foreach($this->_plugins as $plugin) {
						if (method_exists($plugin, 'tick')) {
							$plugin->tick();
						}
					}
					
	 // Don't keep bashing away, have a break...
					usleep(250000);
					
				}
				
			}
			
		}
	
	}
	
 /**
  * Handles server PING/PONG.
  *
  * @param $serverData array Exploded array of the data line from the server.
  * @return boolean True if the given message was a PING (and therefore was replied to); false otherwise.
  */
	private function _handle_server_ping(array $serverData) {

		if ($serverData[0] == 'PING') {
			$this->send_data('PONG '.php_uname('n'));
			return true;
		}

		return false;

	}

 /**
  * Handles server ERROR. Checks if the given message is an ERROR message, and
  * if so shuts down the bot.
  *
  * @param $serverData array Exploded array of the data line from the server.
  */
	private function _handle_server_error(array $serverData) {

		if ($serverData[0] == 'ERROR') {
			$this->_log('Recieved error from server. Terminating.', 'tx');
			exit();
		}

	}
	
 /**
  * Loads all plugins found in the given directory (defaults to plugins/ from
  * the bot's main directory).
  *
  * @param $pluginDirectory string The location to load plugins from. Defaults to 'plugins'.
  */
	private function _load_plugins($pluginDirectory = 'plugins') {
	
		if ($handle = @opendir($pluginDirectory)) {
		
			while (false !== ($file = readdir($handle))) {
				if ('.php' == substr($file, -4)) {
					require_once($pluginDirectory.'/PluginAbstract.php');
					@include_once($pluginDirectory.'/'.$file);
					$pluginName = substr($file, 0, -4);
					if ('PluginAbstract' != $pluginName) {
						if (class_exists($pluginName)) {
							$config = null;
							if (isset($this->_config[$pluginName])) {
								$config = $this->_config[$pluginName];
							}
							$this->_plugins[] = new $pluginName($config, $this);
						}
					}
				}
			}
			
		} else {
			$this->_log('Error loading plugins: unable to open plugins directory');
		}
	
	}
	
 /* Protected methods. */
 
 /**
  * Connects to the given IRC server.
  *
  * @param $server string Hostname of server to connect to.
  * @param $port string Port of server to connect to.
  * @return boolean True on success; false otherwise.
  */
	protected function _connect($server, $port) {

		if (false !== ($this->_connection = stream_socket_client($server.':'.$port))) {
			stream_set_blocking($this->_connection, 0);
			stream_set_timeout($this->_connection, 600);
			return true;
		} else {
			return false;
		}
	
	}
	
 /**
  * Disconnects from the given IRC server.
  */
	protected function _disconnect() { }
	
 /**
  * Logs in to the currently connected IRC server. Note: this method returning
  * true does not necessarily mean that the login was successful.
  *
  * @param $username string Username to use.
  * @param $nickname string Nickname to use.
  * @param $password string Password to use to authenticate (optional).
  * @return boolean True if the login commands were sent to the server successfully; false if it is not.
  */
	protected function _login($username, $nickname, $password = null) {
	
	  if ($this->is_connected()) {

			if (null !== $password) {
				$this->send_data('PASS '.$password);
			}
			$this->send_data('NICK '.$nickname);
			$this->send_data('USER '.$username.' 0 *: '.$username);

			$this->_botNickname = $nickname;
			$this->_botUsername = $username;
			
			return true;
		} else {
			return false;
		}
	
	}
	
 /**
  * Joins a channel on the currently connected IRC server. Note: this method
  * returning true does not necessarily mean that the bot actually entered the
  * channel.
  *
  * @param $channelName string Name of channel to join. Agnostic of leading #.
  * @return boolean True if join message was successfully sent to the server; false if not.
  */
	protected function _join_channel($channelName) {
	
		if (is_string($channelName)) {
			$channelName = $this->add_channel_hash($channelName);
			return $this->send_data('JOIN '.$channelName);
		}
		
		return false;
	
	}
	
 /**
  * Leaves a channel on the currently connected IRC server. Note: this method
  * returning true does not necessarily mean that the bot actually left the
  * channel.
  *
  * @param $channelName string Name of channel to Leave. Agnostic of leading #.
  * @return boolean True if part message was successfully sent to the server; false if not.
  */
	protected function _part_channel($channelName) {

		if (is_string($channelName)) {
			$channelName = $this->add_channel_hash($channelName);
			return $this->send_data('PART '.$channelName);
		}

		return false;

	}
	
 /**
  * Fetches a line of data from the currently connected server.
  *
  * @return string|boolean A line of data from the IRC server on success; false otherwise.
  */
	protected function _get_data() {

	  if ($this->is_connected()) {
			if ($serverData = trim(fgets($this->_connection, 2048))) {
				$this->_log($serverData, 'rx');
			}
			return $serverData;
		} else {
		  return false;
		}

	}

 /**
  * Writes a line to the log. Currently this is simply echoed to standard out
  * (ie. the terminal).
  */
	protected function _log($message, $mode = 'msg') {

		switch ($mode) {
		  case 'msg': break;
			case 'tx':
			  $message = '<Bot to Server> '.$message;
			break;
			case 'rx':
			  $message = '<Server to Bot> '.$message;
			break;
		}

		echo $message.PHP_EOL;

	}
	
 /* Public methods. Plugin API. */
 
 /**
  * Checks to see if the bot is currently connected to a server.
  *
  * @return boolean True if the bot is connected; false if it is not.
  */
	public function is_connected() {

		if (null == $this->_connection) {
			return false;
		}

		return true;

	}
 
 /**
  * Loads (or reloads) the given configuration in-flight.
  *
  * Configuration array must contain:
  *   server => hostname of server to connect to
  *   port => port to connect on
  *   username => name (for username@hostname in WHOIS)
  *   nickname => actual nickname the bot will use
  *
  * @param $config array Configuration array. See above.
  */
	public function load_config(array $config) {

		$this->_config = $config;

	}
	
 /**
  * Sends data to the connected IRC server. If a string is given then a single
  * line is sent, if an array of strings is given then each line is sent
  * individually. If there is an error sending one of the lines, no further
  * lines will be sent.
  *
  * @param $messages string|array Message or messages to send.
  * @return boolean True if all messages are sent to the server; false otherwise.
  */
	public function send_data($messages) {

	  if (is_string($messages)) {
			$messages = array($messages);
		}

		if ($this->is_connected() && is_array($messages)) {
			foreach ($messages AS $message) {
				$this->_log(trim($message), 'tx');
				if (fwrite($this->_connection, trim($message)."\r\n")) {
					foreach ($this->_plugins AS $plugin) {
						if (method_exists($plugin, 'send_message')) {
							$plugin->send_message($message);
						}
					}
				} else {
					$this->_log('Error talking to server.');
					break;
				}
			}
			return true;
		}

		return false;

	}

 /**
  * Restarts all currently loaded plugins, and loads in any new plugins found in
  * the plugins directory.
  *
  * The following actions are carried out:
  *  1. Currently loaded plugins are gracefully shut down
  *  2. The new configuration array is loaded, if given
  *  3. Plugins which were running previously are restarted with the new
  *     configuration but are NOT reloaded from source (ie. code changes made to
  *     the plugin files will not be loaded)
  *  4. Any new plugin files found in the plugins directory are loaded.
  *
  * Note: plugins cannot be unloaded. The bot must be restarted in order to stop
  * a plugin from operating or load changes in plugin code.
  *
  * @param $newConfig array Configuration array. See load_config();
  * @see BrennyBot::load_config()
  */
	public function restart_plugins($newConfig = null) {

	 // Shutdown each of the plugins...
		foreach ($this->_plugins AS $plugin) {
			unset($plugin);
		}

	 // If there's a new config, load that here...
		if (null != $newConfig) {
			$this->load_config($newConfig);
		}

	 // Load the plugins again...
		$this->_load_plugins();

	}
	
 /**
  * Adds the bot to a channel(s). The bot will attemt to join the given
  * channel(s).
  *
  * @param $channels array|string The channel(s) to join. Agnostic of leading #.
  */
	public function add_bot_channel($channels) {
	
		if (is_string($channels)) {
			$channels = array($channels);
		}
		
		foreach ($channels AS $channel) {
			$channel = $this->add_channel_hash($channel);
			if (!array_key_exists($channel, $this->_channels)) {
				$this->_channels[$channel] = false;
			}
		}
	
	}
	
 /**
  * Removes the bot from a channel(s). The bot will attemt to leave the given
  * channel(s).
  *
  * @param $channels array|string The channel(s) to leave. Agnostic of leading #.
  */
	public function remove_bot_channel($channels) {

		if (is_string($channels)) {
			$channels = array($channels);
		}

		foreach ($channels AS $channel) {
			$channel = $this->add_channel_hash($channel);
			if (array_key_exists($channel, $this->_channels)) {
				unset($this->_channels[$channel]);
				$this->_part_channel($channel);
			}
		}

	}
	
 /**
  * Returns the bot's current nickname.
  *
  * @return string The bot's current nickname.
  */
	public function get_nickname() {

	  return $this->_botNickname;

	}
	
 /**
  * Returns the bot's current username.
  *
  * @return string The bot's current username.
  */
	public function get_username() {
	
	  return $this->_botUsername;
	
	}

 /**
  * Adds a command to the array of hooked commands implemented by the currently
  * loaded plugins. If the command is already in the array it will not be over-
  * written, but rather the method will return false.
  *
  * @param $command string Command listened for to add.
  * @param $description string What the command will actually do.
  * @return boolean True if the command was added, false if not.
  */
	public function add_hooked_command($command, $description) {

		if (!array_key_exists($command, $this->_hookedCommands)) {
			$this->_hookedCommands[$command] = $description;
			return true;
		}

		return false;

	}

 /**
  * Removes one or more commands from the array of hooked commands implemented
  * by the currently loaded plugins.
  *
  * @param $command string|array Command listened for to remove.
  * @return boolean True if the command was in the array and removed, false if not.
  */
	public function remove_hooked_command($commands) {

		if (is_string($commands)) {
			$commands = array($commands);
		}

		foreach ($commands AS $command) {
			if (array_key_exists($command, $this->_hookedCommands)) {
				unset($this->_hookedCommands[$command]);
			}
			return true;
		}

		return false;

	}

 /**
  * Returns all currently registered commands implemented by the currently
  * loaded plugins. The returned array looks like this:
  *   'command' => Description of the command (what it does)
  *
  * @return array Hooked commands. See above.
  */
	public function hooked_commands() {

		return $this->_hookedCommands;

	}
	
 /* Public helper methods. */

 /**
  * Parses a user ident as sent by the server.
  *
  * Returns an array containing:
  *   [0] => full ident as given in $userIdent (nickname!~username@remote.host)
  *   [1] => user nickname (nickname)
  *   [2] => user ident (~username@remote.host)
  *
  * @param $userIdent string User ident as sent by the server.
  * @return array Broken down elements of the ident. See above.
  */
	public static function parse_user_ident($userIdent) {

		if (preg_match('/:([a-z<_\-\[\]\^\{\}]+)!(.+)/i', $userIdent, $matches)) {
			return array($matches[0], $matches[1]);
		}

		return false;

	}

 /**
  * Checks the given string for a leading # and adds it if missing.
  *
  * @param $channelName string Channel name to process.
  */
	public static function add_channel_hash($channelName) {

		if ('#' != substr($channelName, 0, 1)) {
			$channelName = '#'.$channelName;
		}

		return $channelName;

	}

}

	 // Run the plugin...
require_once('config.php');
$brennyBot = new BrennyBot($config);