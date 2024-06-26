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

class Post extends AbstractModel
{
    private array $response;

    /*
     * Reference: https://platform.openai.com/docs/api-reference/chat
     *
     * Response:
     * {
          "id": "chatcmpl-123",
          "object": "chat.completion",
          "created": 1677652288,
          "model": "gpt-3.5-turbo-0613",
          "system_fingerprint": "fp_44709d6fcb",
          "choices": [{
            "index": 0,
            "message": {
              "role": "assistant",
              "content": "\n\nHello there, how may I assist you today?",
            },
            "logprobs": null,
            "finish_reason": "stop"
          }],
          "usage": {
            "prompt_tokens": 9,
            "completion_tokens": 12,
            "total_tokens": 21
          }
        }
     */
    private string $url = 'https://api.openai.com/v1/chat/completions';

    public function __construct()
    {
        global $lang, $mybb;

        $lang->load('rt_chatgpt');

        parent::__construct();

        $this->api_timeout = 30;
        $this->maxTokens = 500;
        $this->action = 'OpenAI Assistant - Reply to thread';
        $this->method = 'POST';
        $this->model = $mybb->settings['rt_chatgpt_openai_model'];
        $this->temperature = 0;
        $this->top_p = 1;
        $this->frequency_penalty = 0.0;
        $this->presence_penalty = 0.0;
        $this->stop[] = "Q: ";
        $this->prompt = "I am a highly intelligent question answering bot. I will use MyBB BBCode to output the answer. If you ask me a question that is nonsense, trickery, or has no clear answer, I will respond with \"{$lang->rt_chatgpt_response_negative}\". Q: ";
    }

    public function setRequest(string $message): bool
    {
        try
        {
            $this->response = $this->sendRequest($this->url, $message);

            if (!empty($this->response))
            {
                return true;
            }
        }
        catch (\Exception $e)
        {
            self::logApiStatus($this->action, $e->getMessage(), 0);

            return false;
        }

        return false;
    }

    public function getResponse(): string
    {
        if (!isset($this->response['choices'][0]['message']['content']))
        {
            return '';
        }

        // Log successful api response
        $data = "Message: {$this->response['choices'][0]['message']['content']}";
        self::logApiStatus($this->action, $data, 1, $this->response['id'], $this->response['model'], $this->response['usage']['total_tokens']);

        return $this->response['choices'][0]['message']['content'];
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

        foreach ($cached_data as $row)
        {
            $query = $db->simple_select('posts', '*', "pid = '{$db->escape_string($row['pid'])}'");
            $post = $db->fetch_array($query);

            // Post not found in DB
            if (!$post)
            {
                continue;
            }

            // Send request to the API
            $openai = $this->setRequest($row['message']);

            // Failed to retrieve data from API
            if (!$openai)
            {
                self::logApiStatus($this->action, 'Unable to send setRequest() method.', 0);
                continue;
            }

            // Get API answer
            $message = $this->getResponse();

            // Failed to retrieve message from API
            if (empty($message))
            {
                self::logApiStatus($this->action, 'Unable to receive data from getResponse() method.', 0);
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
        }

        $cache->delete('rt_chatgpt_reply');
    }
}
