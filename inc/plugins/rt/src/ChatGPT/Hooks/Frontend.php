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

final class Frontend
{

    /**
     * Hook: newthread_do_newthread_end
     *
     * @return void
     */
    public function newthread_do_newthread_end(): void
    {
        global $mybb, $lang, $new_thread, $tid;

        // Reply to the thread with OpenAI
        if (Core::can_bot_reply_to_thread() &&
            (in_array($new_thread['fid'], \rt\ChatGPT\get_settings_values('assistant_forums')) || in_array(-1, \rt\ChatGPT\get_settings_values('assistant_forums')))
        )
        {
            $lang->load('rt_chatgpt');

            $bot = get_user((int) $mybb->settings['rt_chatgpt_assistant_bot_id']);
            $forumpermissions = forum_permissions((int) $new_thread['fid'], (int) $mybb->settings['rt_chatgpt_assistant_bot_id']);

            // Check if bot is moderator or is user
            if (!$bot || (int) $forumpermissions['canpostreplys'] === 0)
            {
                return;
            }

            // Set up posthandler.
            require_once MYBB_ROOT."inc/datahandlers/post.php";

            $posthandler = new \PostDataHandler("insert");
            $posthandler->action = "post";

            // Set the post data that came from the input to the $post array.
            $post = [
                'tid' => (int) $tid,
                'fid' => (int) $new_thread['fid'],
                'subject' => $new_thread['subject'],
                'replyto' => 0,
                'icon' => '',
                'uid' => (int) $mybb->settings['rt_chatgpt_assistant_bot_id'],
                'message' => $lang->rt_chatgpt_wait_response,
                'ipaddress' => my_inet_pton('127.0.0.1'),
                'posthash' => md5($mybb->settings['rt_chatgpt_assistant_bot_id'].random_str()),
                'savedraft' => 0,
                'visible' => 0 // post will be invisible to general members at insertion
            ];

            // Set up the post options from the input.
            $post['options'] = [
                'signature' => 1,
                'subscriptionmethod' => 0,
                'disablesmilies' => 0,
            ];

            $posthandler->set_data($post);

            if ($posthandler->validate_post())
            {
                $post_details = [
                    'pid' => (int) $posthandler->insert_post()['pid'],
                    'tid' => (int) $tid,
                    'message' => $new_thread['message'],
                ];
                (new \rt\ChatGPT\Models\ThreadModel())->cacheNewReply($post_details);
            }
        }

        // Moderate thread
        if (Core::thread_will_go_through_moderation_check() &&
            (in_array($new_thread['fid'], \rt\ChatGPT\get_settings_values('moderation_forums')) || in_array(-1, \rt\ChatGPT\get_settings_values('moderation_forums')))
        )
        {
            $data = [
                'tid' => $tid,
                'subject' => $new_thread['subject'],
                'message' => $new_thread['message'],
            ];

            (new \rt\ChatGPT\Models\ModerationModel())->cacheThreadForModeration($data);
        }
    }
}