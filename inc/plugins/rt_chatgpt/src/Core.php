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

class Core
{
    public static array $PLUGIN_DETAILS = [
        'name' => 'RT ChatGPT',
        'website' => 'https://github.com/RevertIT/mybb-rt_chatgpt',
        'description' => 'RT ChatGPT utilizes OpenAI API to generate responses and do tasks.<br><br><a href="index.php?module=tools-rt_chatgpt"><strong>ChatGPT Tools</strong></a>',
        'author' => 'RevertIT',
        'authorsite' => 'https://github.com/RevertIT/',
        'version' => '0.1',
        'compatibility' => '18*',
        'codename' => 'rt_chatgpt',
        'prefix' => 'rt_chatgpt',
    ];

    /**
     * Get plugin info
     *
     * @param string $info
     * @return string
     */
    public static function get_plugin_info(string $info): string
    {
        return self::$PLUGIN_DETAILS[$info] ?? '';
    }

    /**
     * Check if plugin is installed
     *
     * @return bool
     */
    public static function is_installed(): bool
    {
        global $mybb;

        if (isset($mybb->settings['rt_chatgpt_enabled']))
        {
            return true;
        }

        return false;
    }

    public static function is_enabled(): bool
    {
        return self::is_installed();
    }

    public static function can_bot_reply_to_thread(): bool
    {
        global $mybb;

        if (isset($mybb->settings['rt_chatgpt_enable_assistant'], $mybb->settings['rt_chatgpt_assistant_bot_id']) &&
            (int) $mybb->settings['rt_chatgpt_enable_assistant'] === 1 &&
            (int) $mybb->settings['rt_chatgpt_assistant_bot_id'] > 0
        )
        {
            return true;
        }

        return false;
    }

    /**
     * Add settings
     *
     * @return void
     */
    public static function add_settings(): void
    {
        global $PL;

        $PL->settings("rt_chatgpt",
            "RT ChatGPT Assistant",
            "Setting group for the RT ChatGPT Assistant plugin.",
            [
                "enabled" => [
                    "title" => "Enable ChatGPT plugin?",
                    "description" => "Enable ChatGPT to use OpenAPI.",
                    "optionscode" => "yesno",
                    "value" => 1
                ],
                "open_api_key" => [
                    "title" => "OpenApi key",
                    "description" => "Enter an OpenAI API key. You can generate api key by <a href=\"https://platform.openai.com/account/api-keys\" target=\"_blank\">clicking here</a>.",
                    "optionscode" => "text",
                    "value" => ""
                ],
                "enable_assistant" => [
                    "title" => "ChatGPT Assistant",
                    "description" => "This option will use a specific user id to respond in specific forums.",
                    "optionscode" => "yesno",
                    "value" => 0
                ],
                "assistant_bot_id" => [
                    "title" => "[ChatGPT Assistant] - User ID",
                    "description" => "Set a bot user id which will respond into thread. (The bot will respond into thread regardless of the permissions)",
                    "optionscode" => "numeric",
                    "value" => 1,
                ],
                "assistant_forums" => [
                    "title" => "[ChatGPT Assistant] - Forums to watch",
                    "description" => "Select which forums should the bot watch and generate responses to. (Do not select a lot of forums as it will drain up api limits)",
                    "optionscode" => "forumselect",
                    "value" => '',
                ],
            ],
        );
    }

    public static function remove_settings()
    {
        global $PL;

        $PL->settings_delete('rt_chatgpt', true);
    }

    public static function add_database_modifications(): void
    {
        global $db;

        switch ($db->type)
        {
            case 'pgsql':
                $db->write_query("
				 CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "rt_chatgpt_logs (
                    id serial,
                    message text NULL,
                    action text NULL,
                    status int NULL,
                    dateline int NOT NULL,
                    PRIMARY KEY (id)
                );
				");
                $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "rt_chatgpt_config (
                    id serial,
                    model_action text NULL,
                    model text NULL,
                    temperature text NULL,
                    top_p text NULL,
                    frequency_penalty text NULL,
                    presence_penalty text NULL,
                    prompt text NULL,
                    max_tokens int NULL,
                    PRIMARY KEY (id)
                );
            	");
                break;
            case 'sqlite':
                $db->write_query("
				 CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "rt_chatgpt_logs (
                    id integer primary key,
                    message text NULL,
                    action text NULL,
                    status integer NULL,
                    dateline integer NOT NULL,
                );
				");
                $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "rt_chatgpt_config (
                    id integer primary key,
                    model_action text NULL,
                    model text NULL,
                    temperature text NULL,
                    top_p text NULL,
                    frequency_penalty text NULL,
                    presence_penalty text NULL,
                    prompt text NULL,
                    max_tokens integer NULL,
                );
            	");
                break;
            default:
                $db->write_query("
				CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "rt_chatgpt_logs (
                    id int(11) NOT NULL auto_increment,
                    message text NULL,
                    action text NULL,
                    status int NULL,
                    dateline int(11) NOT NULL,
                    PRIMARY KEY (id)
                );
				");
                $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "rt_chatgpt_config (
                    id int(11) NOT NULL auto_increment,
                    model_action text NULL,
                    model text NULL,
                    temperature text NULL,
                    top_p text NULL,
                    frequency_penalty text NULL,
                    presence_penalty text NULL,
                    prompt text NULL,
                    max_tokens int(11) NULL,
                    PRIMARY KEY (id)
                );
            	");
                break;
        }
    }

    public static function remove_database_modifications(): void
    {
        global $db;

        $db->drop_table('rt_chatgpt_logs');
        $db->drop_table('rt_chatgpt_config');
    }
}