<?php
/**
 * RT ChatGPT Assistant
 *
 * RT ChatGPT utilizes OpenAI API to generate responses and do tasks.
 *
 * @package rt_chatgpt
 * @author  RevertIT <https://github.com/revertit>
 * @license http://opensource.org/licenses/mit-license.php MIT license
 */

declare(strict_types=1);

namespace rt\ChatGPT;

use Exception;

/**
 * Autoload plugin hooks
 *
 * @param array $class Array of classes to load for hooks
 * @return void
 */
function autoload_plugin_hooks(array $class): void
{
    global $plugins;

    foreach ($class as $hook)
    {
        if (!class_exists($hook))
        {
            continue;
        }

        $class_methods = get_class_methods(new $hook());

        foreach ($class_methods as $method)
        {
            $plugins->add_hook($method, [new $hook(), $method]);
        }
    }
}

/**
 * PHP version check
 *
 * @return void
 */
function check_php_version(): void
{
    if (version_compare(PHP_VERSION, '7.4.0', '<'))
    {
        flash_message("PHP version must be at least 7.4 due to security reasons.", "error");
        admin_redirect("index.php?module=config-plugins");
    }
}

/**
 * PluginLibrary loader
 *
 * @return void
 */
function load_pluginlibrary(): void
{
    global $PL, $config, $mybb;

    if (!defined('PLUGINLIBRARY'))
    {
        define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');
    }

    if (file_exists(PLUGINLIBRARY))
    {
        if (!$PL)
        {
            require_once PLUGINLIBRARY;
        }
        if (version_compare((string) $PL->version, '13', '<'))
        {
            Core::$PLUGIN_DETAILS['description'] .= <<<DESC
			<br/>
			<b style="color: orange">
				<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/warning.png" alt="">
				PluginLibrary version is outdated. You can update it by <a href="https://community.mybb.com/mods.php?action=view&pid=573" target="_blank">clicking here</a>.
			</b>
			DESC;
        }
        else
        {
            Core::$PLUGIN_DETAILS['description'] .= <<<DESC
			<br/>
			<b style="color: green">
				<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/tick.png" alt="">
				PluginLibrary (ver-{$PL->version}) is installed.
			</b>
			DESC;
        }
    }
    else
    {
        Core::$PLUGIN_DETAILS['description'] .= <<<DESC
		<br/>
		<b style="color: orange">
			<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/warning.png" alt="">
			PluginLibrary is missing. You can download it by <a href="https://community.mybb.com/mods.php?action=view&pid=573" target="_blank">clicking here</a>.
		</b>
		DESC;
    }
}

/**
 * PluginLibrary install checker
 *
 * @return void
 */
function check_pluginlibrary(): void
{
    global $PL;

    if (!defined('PLUGINLIBRARY'))
    {
        define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');
    }

    if (file_exists(PLUGINLIBRARY))
    {
        if (!$PL)
        {
            require_once PLUGINLIBRARY;
        }
        if (version_compare((string) $PL->version, '13', '<'))
        {
            flash_message("PluginLibrary version is outdated. You can update it by <a href=\"https://community.mybb.com/mods.php?action=view&pid=573\">clicking here</a>.", "error");
            admin_redirect("index.php?module=config-plugins");
        }
    }
    else
    {
        flash_message("PluginLibrary is missing. You can download it by <a href=\"https://community.mybb.com/mods.php?action=view&pid=573\">clicking here</a>.", "error");
        admin_redirect("index.php?module=config-plugins");
    }
}

/**
 * Plugin version loader
 *
 * @return void
 */
function load_plugin_version(): void
{
    global $cache, $mybb, $config;

    $cached_version = $cache->read(Core::get_plugin_info('prefix'));
    $current_version = Core::get_plugin_info('version');

    if (isset($cached_version['version'], $current_version))
    {
        if (version_compare($cached_version['version'], Core::get_plugin_info('version'), '<'))
        {
            Core::$PLUGIN_DETAILS['description'] .= <<<DESC
			<br/>
			<b style="color: orange">
			<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/warning.png" alt="">
			RT ChatGPT version missmatch. You need to deactivate and activate plugin again.
			</b>
			DESC;
        }
        else
        {
            // Add link for tools management
            if (Core::is_enabled() && Core::is_installed())
            {
                Core::$PLUGIN_DETAILS['description'] .= <<<DESC
                <br><br><a href="index.php?module=tools-rt_chatgpt"><strong>ChatGPT Tools</strong></a>
                DESC;
            }

            Core::$PLUGIN_DETAILS['description'] .= <<<DESC
			<br/>
			<b style="color: green">
			<img src="{$mybb->settings['bburl']}/{$config['admin_dir']}/styles/default/images/icons/tick.png" alt="">
			RT ChatGPT (ver-{$current_version}) is up-to-date and ready for use.
			</b>
			DESC;
        }
    }
}

/**
 * Fetch api request
 *
 * @param string $url
 * @param string $method
 * @param array $data
 * @param array $headers
 * @param int $max_redirects
 * @param int $timeout
 *
 * @return bool|string
 * @throws Exception
 */
function fetch_api(string $url, string $method = 'GET', array $data = [], array $headers = [], int $max_redirects = 10, int $timeout = 5): ?string
{
    $curl_info = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => $max_redirects,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER     => $headers,
    ];

    $allowed_methods = ['GET', 'POST', 'PUT', 'DELETE'];

    if (in_array($method, $allowed_methods))
    {
        $curl_info[CURLOPT_CUSTOMREQUEST] =  strtoupper($method);

        // Set the data if it's a PUT or POST request
        if ($method === 'PUT' || $method === 'POST')
        {
            $curl_info[CURLOPT_POSTFIELDS] = json_encode($data);
        }
    }

    $curl = curl_init();

    curl_setopt_array($curl, $curl_info);

    $response = curl_exec($curl);

    // Check for any errors
    if (curl_errno($curl))
    {
        $error = curl_error($curl);
        curl_close($curl);
        throw new Exception($error);
    }

    curl_close($curl);

    return $response;
}

/**
 * make comma separated permissions as array
 *
 * @param string $name Settings name
 * @return array
 */
function get_settings_values(string $name): array
{
    global $mybb;

    return array_filter(
        explode(',', $mybb->settings[Core::get_plugin_info('prefix') . '_' . $name] ?? ''),
        'strlen'
    );
}