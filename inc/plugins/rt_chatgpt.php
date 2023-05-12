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

require MYBB_ROOT . 'inc/plugins/rt_chatgpt/src/functions.php';
require MYBB_ROOT . 'inc/plugins/rt_chatgpt/src/Core.php';
require MYBB_ROOT . 'inc/plugins/rt_chatgpt/src/Models/AbstractModel.php';
require MYBB_ROOT . 'inc/plugins/rt_chatgpt/src/Models/Post.php';
require MYBB_ROOT . 'inc/plugins/rt_chatgpt/src/Hooks/Frontend.php';

// Hooks manager
if (defined('IN_ADMINCP'))
{
    require MYBB_ROOT . 'inc/plugins/rt_chatgpt/src/Hooks/Backend.php';
}

\rt\ChatGPT\autoload_plugin_hooks([
    '\rt\ChatGPT\Hooks\Frontend',
    '\rt\ChatGPT\Hooks\Backend',
]);

function rt_chatgpt_info(): array
{
    return \rt\ChatGPT\Core::$PLUGIN_DETAILS;
}

function rt_chatgpt_install(): void
{
    \rt\ChatGPT\check_php_version();
    \rt\ChatGPT\load_pluginlibrary();

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
    \rt\ChatGPT\load_pluginlibrary();

    \rt\ChatGPT\Core::remove_settings();
    \rt\ChatGPT\Core::remove_database_modifications();
    \rt\ChatGPT\Core::remove_task();

}

function rt_chatgpt_activate(): void
{
    \rt\ChatGPT\check_php_version();
    \rt\ChatGPT\load_pluginlibrary();

    \rt\ChatGPT\Core::add_settings();
}

function rt_chatgpt_deactivate(): void
{
    \rt\ChatGPT\check_php_version();
    \rt\ChatGPT\load_pluginlibrary();
}