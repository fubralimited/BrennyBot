BrennyBot
=========

A flexible, extendable IRC bot written in PHP.

Plugins
-------

BrennyBot has a flexible plugin system allowing the bot to be extended to carry out tasks and respond to user input. The main bot provides a number of public methods to interact with the IRC server and get data from the bot, these are documented in the API section below.

The plugin structure was inspired by (and occationally borrowing from) [Ueland's](https://github.com/Ueland) [VikingBot](https://github.com/Ueland/VikingBot).

### API

#### is_connected()
Checks to see if the bot is currently connected to a server.

Return: true if the bot is connected; false if it is not.

#### load_config(array $config)
Loads (or reloads) the given configuration in-flight.

Configuration array must contain:
 server => hostname of server to connect to
 port => port to connect on
 username => name (for username@hostname in WHOIS)
 nickname => actual nickname the bot will use

Return: null

#### send_data($messages)
Sends data to the connected IRC server. If a string is given then a single line is sent, if an array of strings is given then each line is sent individually. If there is an error sending one of the lines, no further lines will be sent.

Return: true if all messages are sent to the server; false otherwise.

#### restart_plugins($newConfig = null)
Restarts all currently loaded plugins, and loads in any new plugins found in the plugins directory.

The following actions are carried out:
1. Currently loaded plugins are gracefully shut down
2. The new configuration array is loaded, if given
3. Plugins which were running previously are restarted with the new
   configuration but are NOT reloaded from source (ie. code changes made to
   the plugin files will not be loaded)
4. Any new plugin files found in the plugins directory are loaded.

Note: plugins cannot be unloaded. The bot must be restarted in order to stop a plugin from operating or load changes in plugin code.

#### add_bot_channel($channels)
Adds the bot to a channel(s). The bot will attemt to join the given channel(s).

#### get_nickname()

Returns the bot's current nickname.

#### get_username()

Returns the bot's current username.

#### add_hooked_command($command, $description)
Adds a command to the array of hooked commands implemented by the currently loaded plugins. If the command is already in the array it will not be over-written, but rather the method will return false.

Return: True if the command was added, false if not.

#### remove_hooked_command($commands)
Removes one or more commands from the array of hooked commands implemented by the currently loaded plugins. Specify either a string or array of strings.

Return: True if the command was in the array and removed, false if not.

#### hooked_commands()
Returns all currently registered commands implemented by the currently loaded plugins. The returned array looks like this:
  'command' => Description of the command (what it does)

### Bundled / Basic Plugins

#### BotStatistics
Implements a set of commands to return data and statistics about the current instance of the bot.

* !memory - Reports the current and peak memory usage of the bot.
* !uptime - Reports the current uptime of the bot.

#### ChannelLogger
Logs activity in channels.

#### Help
Adds a command to output all currently registered plugin commands.
