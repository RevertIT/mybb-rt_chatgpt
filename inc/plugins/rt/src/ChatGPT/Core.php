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
        'description' => 'RT ChatGPT utilizes OpenAI API to generate responses and do specific tasks. <b>This plugin uses task system which will run every 5 minutes.</b>',
        'author' => 'RevertIT',
        'authorsite' => 'https://github.com/RevertIT/',
        'version' => '1.3',
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
        global $mybb;

        return isset($mybb->settings['rt_chatgpt_enabled']) && (int) $mybb->settings['rt_chatgpt_enabled'] === 1;
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

    public static function thread_will_go_through_moderation_check(): bool
    {
        global $mybb;

        if (isset($mybb->settings['rt_chatgpt_enable_moderation'], $mybb->settings['rt_chatgpt_moderation_forums'], $mybb->settings['rt_chatgpt_moderation_usergroups']) &&
            is_member($mybb->settings['rt_chatgpt_moderation_usergroups'], $mybb->user['uid']) &&
            (int) $mybb->settings['rt_chatgpt_enable_moderation'] === 1
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
                    "description" => "Enable ChatGPT to use OpenAI.",
                    "optionscode" => "yesno",
                    "value" => 1
                ],
                "openai_key" => [
                    "title" => "OpenAI API key",
                    "description" => "Enter an OpenAI API key. You can generate api key by <a href=\"https://platform.openai.com/account/api-keys\" target=\"_blank\">clicking here</a>.",
                    "optionscode" => "text",
                    "value" => ""
                ],
                "openai_model" => [
                    "title" => "OpenAI Model",
                    "description" => "Select which model will be used when generating an API responses to the posts/threads",
                    "optionscode" => <<<SELECT
                    select
                    gpt-4o=GPT-4o (New)
                    gpt-4=GPT-4
                    gpt-4-turbo=GPT-4 Turbo
                    gpt-3.5-turbo=GPT 3.5 Turbo
                    gpt-3.5-turbo-16k=GPT 3.5 Turbo (version with larger context window)
                    SELECT,
                    "value" => "gpt-3.5-turbo",
                ],
                "enable_assistant" => [
                    "title" => "ChatGPT Assistant",
                    "description" => "This option will use a specific user id to respond in specific forums.",
                    "optionscode" => "yesno",
                    "value" => 0
                ],
                "assistant_bot_id" => [
                    "title" => "[ChatGPT Assistant] - User ID",
                    "description" => "Set a bot user id which will respond into thread. (<b>The bot needs permissions to respond in forums you select below</b>)",
                    "optionscode" => "numeric",
                    "value" => 1,
                ],
                "assistant_forums" => [
                    "title" => "[ChatGPT Assistant] - Forums to watch",
                    "description" => "Select which forums should the bot watch and generate responses to. (Do not select a lot of forums as it will drain up api limits)",
                    "optionscode" => "forumselect",
                    "value" => '',
                ],
                "enable_moderation" => [
                    "title" => "ChatGPT Thread moderation",
                    "description" => "This option will use a specific AI module to check whether post contains harmful/hateful/offensive content and put it into moderation for review.",
                    "optionscode" => "yesno",
                    "value" => 0
                ],
                "moderation_model" => [
                    "title" => "[ChatGPT Moderation] - Moderation model",
                    "description" => "Select by comma separation which content should the ChatGPT check for:
					  <br>1 = hate
					  <br>2 = hate/threatening
					  <br>3 = self-harm
					  <br>4 = sexual
					  <br>5 = sexual/minors
					  <br>6 = violence
					  <br>7 = violence/graphic",
                    "optionscode" => "text",
                    "value" => "1,2,5,6,7"
                ],
                "moderation_forums" => [
                    "title" => "[ChatGPT Moderation] - Forums to watch",
                    "description" => "Select which forums should the ChatGPT look for and filter the newly posted content based on moderation score.",
                    "optionscode" => "forumselect",
                    "value" => '',
                ],
                "moderation_usergroups" => [
                    "title" => "[ChatGPT Moderation] - Usergroups to watch",
                    "description" => "Select which usergroups should the ChatGPT watch and moderate. (Selected groups will always have all posts checked before they publicly appear)",
                    "optionscode" => "groupselect",
                    "value" => '0,1',
                ],
            ],
        );
    }

    public static function remove_settings(): void
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
                    oid text NULL,
                    model text NULL,
                    used_tokens int NULL,
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
                    oid text NULL,
                    model text NULL,
                    used_tokens integer NULL,
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
                    oid text NULL,
                    model text NULL,
                    used_tokens int NULL,
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
        global $db, $mybb, $page, $lang;


        if ($mybb->request_method !== 'post')
        {
            $lang->load('rt_chatgpt');

            $page->output_confirm_action('index.php?module=config-plugins&action=deactivate&uninstall=1&plugin=' . self::$PLUGIN_DETAILS['prefix'], $lang->rt_chatgpt_uninstall_message, $lang->uninstall);
        }

        // Drop tables
        if (!isset($mybb->input['no']))
        {
            $db->drop_table('rt_chatgpt_logs');
            $db->drop_table('rt_chatgpt_config');
        }
    }

    public static function add_task(): void
    {
        global $db, $cache;

        // tasks
        $task = [
            'title'       => 'RT ChatGPT',
            'description' => 'Performs scheduled operations for RT ChatGPT.',
            'file'        => 'rt_chatgpt',
            'minute'      => '1,6,11,16,21,26,31,36,41,46,51,56',
            'hour'        => '*',
            'day'         => '*',
            'month'       => '*',
            'weekday'     => '*',
            'enabled'     => '1',
            'logging'     => '1',
        ];

        require_once MYBB_ROOT . '/inc/functions_task.php';

        $task['nextrun'] = fetch_next_run($task);
        $db->insert_query('tasks', $task);

        $cache->update_tasks();
    }

    public static function remove_task(): void
    {
        global $db;

        $db->delete_query('tasks', 'file = "rt_chatgpt"');
    }

    /**
     * Set plugin cache
     *
     * @return void
     */
    public static function set_cache(): void
    {
        global $cache;

        if (!empty(self::$PLUGIN_DETAILS))
        {
            $cache->update(self::$PLUGIN_DETAILS['prefix'], self::$PLUGIN_DETAILS);
        }
    }

    /**
     * Remove plugin cache
     *
     * @return void
     */
    public static function remove_cache()
    {
        global $cache;

        $cache->delete('rt_chatgpt');
        $cache->delete('rt_chatgpt_reply');
        $cache->delete('rt_chatgpt_moderation');
    }
}