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

// Disallow direct access to this file for security reasons
if (!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

// Autoload classes
require_once MYBB_ROOT . 'inc/plugins/rt/vendor/autoload.php';

\rt\Autoload\psr4_autoloader(
    'rt',
    'src',
    'rt\\ChatGPT\\',
    [
        'rt/ChatGPT/functions.php',
    ]
);

$hooks = [];
// Hooks manager
if (defined('IN_ADMINCP'))
{
    $hooks[] = '\rt\ChatGPT\Hooks\Backend';
}
if (\rt\ChatGPT\Core::is_enabled())
{
    $hooks[] = '\rt\ChatGPT\Hooks\Frontend';
}

// Autoload plugin hooks
\rt\ChatGPT\autoload_plugin_hooks($hooks);

// Health checks
\rt\ChatGPT\load_plugin_version();
\rt\ChatGPT\load_pluginlibrary();

function rt_chatgpt_info(): array
{
    return \rt\ChatGPT\Core::$PLUGIN_DETAILS;
}

function rt_chatgpt_install(): void
{
    \rt\ChatGPT\check_php_version();
    \rt\ChatGPT\check_pluginlibrary();

    \rt\ChatGPT\Core::set_cache();
    \rt\ChatGPT\Core::add_database_modifications();
    \rt\ChatGPT\Core::add_task();
}

function rt_chatgpt_is_installed(): bool
{
    return \rt\ChatGPT\Core::is_installed();
}

function rt_chatgpt_uninstall(): void
{
    \rt\ChatGPT\check_php_version();
    \rt\ChatGPT\check_pluginlibrary();

    \rt\ChatGPT\Core::remove_settings();
    \rt\ChatGPT\Core::remove_database_modifications();
    \rt\ChatGPT\Core::remove_task();
    \rt\ChatGPT\Core::remove_cache();
}

function rt_chatgpt_activate(): void
{
    \rt\ChatGPT\check_php_version();
    \rt\ChatGPT\check_pluginlibrary();

    \rt\ChatGPT\Core::set_cache();
    \rt\ChatGPT\Core::add_settings();
}

function rt_chatgpt_deactivate(): void
{
    \rt\ChatGPT\check_php_version();
    \rt\ChatGPT\check_pluginlibrary();
}