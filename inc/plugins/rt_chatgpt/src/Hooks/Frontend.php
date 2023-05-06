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

namespace rt\ChatGPT\Hooks;

use rt\ChatGPT\Core;
use rt\ChatGPT\Models\Post;

final class Frontend
{
    public function global_start()
    {
        global $mybb;

    }

    /**
     * Hook: newthread_do_newthread_end
     *
     * @return void
     */
    public function newthread_do_newthread_end(): void
    {
        global $mybb, $db, $lang, $new_thread, $tid, $username;

        // Reply to the thread with OpenAI
        if (Core::can_bot_reply_to_thread() &&
            (in_array($new_thread['fid'], \rt\ChatGPT\get_settings_values('assistant_forums')) || in_array(-1, \rt\ChatGPT\get_settings_values('assistant_forums')))
        )
        {
            // OpenAI request
            $openai = new Post($new_thread['message']);
            $response = $openai->getResponse();

            // Insert post
            if (!empty($response))
            {
                $user = get_user($mybb->settings['rt_chatgpt_assistant_bot_id']);

                if (!$user)
                {
                    return;
                }

                $insert_post = [
                    "tid" => (int) $tid,
                    "fid" => (int) $new_thread['fid'],
                    "subject" => $db->escape_string($new_thread['subject']),
                    "icon" => 0,
                    "uid" => (int) $mybb->settings['rt_chatgpt_assistant_bot_id'],
                    "username" => $db->escape_string($user['username']),
                    "dateline" => TIME_NOW,
                    "message" => $db->escape_string($response),
                    "ipaddress" => $db->escape_binary('127.0.0.1'),
                    "includesig" => 1,
                    "smilieoff" => 1,
                    "visible" => 1
                ];

                $db->insert_query("posts", $insert_post);
            }
        }
    }
}