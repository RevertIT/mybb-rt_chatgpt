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

class Moderation extends AbstractModel
{
    private array $response;
    private string $url = 'https://api.openai.com/v1/moderations';

    public function __construct()
    {
        parent::__construct();

        $this->action = 'OpenAI Assistant - Thread moderation';
        $this->method = 'POST';
    }

    public function setRequest(string $message): bool
    {
        $this->input = $message;
        $this->response = $this->sendRequest($this->url, $message);

        if (!empty($this->response))
        {
            return true;
        }

        return false;
    }

    public function cacheThreadForModeration(array $thread): bool
    {
        global $cache;

        $data = $cache->read('rt_chatgpt_moderation');
        $data[] = $thread;

        $cache->update('rt_chatgpt_moderation', $data);

        return true;
    }

    public function getResponse(): array
    {
        if (!isset($this->response['results']))
        {
            return [];
        }

        // Log successful api response
        $api_score = json_encode($this->response['results'][0]['category_scores']);
        self::logApiStatus($this->action, $api_score, 1, $this->response['id']);

        return $this->response;
    }

    public function moderateThread(): void
    {
        global $cache, $db;

        $cached_data = $cache->read('rt_chatgpt_moderation');

        if (empty($cached_data))
        {
            return;
        }

        require_once MYBB_ROOT."inc/class_moderation.php";
        $thread_moderation = new \Moderation();

        foreach ($cached_data as $row)
        {
            $query = $db->simple_select('threads', '*', "tid = '{$db->escape_string($row['tid'])}'");
            $thread = $db->fetch_array($query);

            // Post not found in DB
            if (!$thread)
            {
                continue;
            }

            $message = "{$row['subject']} - {$row['message']}";

            // Send request to the API
            $openai = $this->setRequest($message);

            // Failed to retrieve data from API
            if (!$openai)
            {
                continue;
            }

            // Get API answer
            $moderation = $this->getResponse();

            // Failed to retrieve message from API
            if (empty($moderation))
            {
                continue;
            }

            // Moderate thread
            $flagged_settings = \rt\ChatGPT\get_settings_values('moderation_model');
            $openai_categories = array_values($moderation['results'][0]['categories']);

            // Loop through settings with flagged selected options
            foreach ($flagged_settings as $setting)
            {
                // Check if flagged option is set and check if it is flagged via API
                if (isset($openai_categories[$setting]) && (int) $openai_categories[$setting] === 1)
                {
                    $thread_moderation->unapprove_threads($row['tid']);
                    break;
                }
            }
        }

        // Clear moderation queue
        $cache->delete('rt_chatgpt_moderation');
    }
}