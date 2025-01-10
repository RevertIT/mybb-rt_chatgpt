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

namespace rt\ChatGPT\Models;

use MyBB;
use rt\ChatGPT\Models\OpenAi\OpenAiModel;

class ThreadModel extends OpenAiModel
{
    public function __construct()
    {
        global $lang;

        $lang->load('rt_chatgpt');
        parent::__construct();
    }


    public function cacheNewReply(array $newData): bool
    {
        global $cache;

        $data = $cache->read('rt_chatgpt_reply');
        $data[] = $newData;

        $cache->update('rt_chatgpt_reply', $data);

        return true;
    }

    public function updateReplyWithAiResponse(): void
    {
        global $cache, $db;

        $cached_data = $cache->read('rt_chatgpt_reply');

        if (empty($cached_data))
        {
            return;
        }

        // Set up posthandler.
        require_once MYBB_ROOT."inc/datahandlers/post.php";

		$toRemove = [];
        foreach ($cached_data as $key => $row)
        {
            $query = $db->simple_select('posts', '*', "pid = '{$db->escape_string($row['pid'])}'");
            $post = $db->fetch_array($query);

            // ThreadModel not found in DB
            if (!$post)
            {
				$toRemove[] = $row['pid'];
                continue;
            }

            try
            {
                // Send request to the API
                $message = $this->chatCompletionReplyToThread($row['message']);
            }
            catch (\Exception $e)
            {
                continue;
            }

            $posthandler = new \PostDataHandler("update");
            $posthandler->action = "post";

            // Set the post data that came from the input to the $post array.
            $post = [
                'pid' => (int) $post['pid'],
                'subject' => $post['subject'],
                'icon' => '',
                'uid' => (int) $post['uid'],
                'message' => $message,
                'edit_uid' => (int) $post['uid'],
                'visible' => 1
            ];

            // Set up the post options from the input.
            $post['options'] = [
                'signature' => 1,
                'subscriptionmethod' => 0,
                'disablesmilies' => 0,
            ];

            $posthandler->set_data($post);
            if (!$posthandler->validate_post())
            {
                $post_errors = $posthandler->get_friendly_errors();
                $post_errors = inline_error($post_errors);
                self::logApiStatus($this->action, $post_errors, 0);
            }
            else
            {
                $posthandler->update_post();
            }

			$toRemove[] = $row['pid'];
        }

		// Remove from cache only replies which were successfully updated from api or post doesn't exist
		if (!empty($toRemove))
		{
			foreach ($toRemove as $pid)
			{
				$cached_data = array_filter($cached_data, function ($row) use ($pid)
				{
					return $row['pid'] !== $pid;
				});
			}

			$cache->update('rt_chatgpt_reply', $cached_data);
		}
	}
}
