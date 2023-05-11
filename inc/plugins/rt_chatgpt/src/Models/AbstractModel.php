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

abstract class AbstractModel
{
    protected string $method;
    protected string $api_key;
    protected string $model;
    protected string $prompt;
    protected float $temperature;
    protected int $maxTokens;
    protected float $top_p;
    protected float $frequency_penalty;
    protected float $presence_penalty;
    protected array $stop;
    protected string $action;
    private array $headers;
    protected string $input;
    protected int $api_timeout;

    protected function __construct()
    {
        global $mybb;

        $this->api_key = $mybb->settings['rt_chatgpt_open_api_key'] ?? '';

        $this->headers = [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->api_key}",
        ];
    }


    /**
     * @param string $url
     * @param string $message
     * @return array|null
     */
    protected function sendRequest(string $url, string $message): array
    {
        $opts = [];
        if (isset($this->method))
        {
            $opts['method'] = $this->method;
        }
        $opts['headers'] = $this->headers;

        $opts['max_redirects'] = 10;

        $opts['timeout'] = 5;
        if (isset($this->api_timeout))
        {
            $opts['timeout'] = $this->api_timeout;
        }
        if (isset($this->temperature))
        {
            $opts['data']['temperature'] = $this->temperature;
        }
        if (isset($this->model))
        {
            $opts['data']['model'] = $this->model;
        }
        if (isset($this->prompt))
        {
            $opts['data']['prompt'] = $this->prompt . $message;
        }
        if (isset($this->top_p))
        {
            $opts['data']['top_p'] = $this->top_p;
        }
        if (isset($this->frequency_penalty))
        {
            $opts['data']['frequency_penalty'] = $this->frequency_penalty;
        }
        if (isset($this->presence_penalty))
        {
            $opts['data']['presence_penalty'] = $this->presence_penalty;
        }
        if (isset($this->maxTokens))
        {
            $opts['data']['max_tokens'] = $this->maxTokens;
        }
        if (isset($this->stop))
        {
            $opts['data']['stop'] = $this->stop;
        }
        if (isset($this->input))
        {
            $opts['data']['input'] = $this->input;
        }

        try
        {
            $apiResponse = \rt\ChatGPT\fetch_api($url, $opts['method'], $opts['data'], $opts['headers'], $opts['max_redirects'], $opts['timeout']);

            $json = json_decode($apiResponse, true);

            if (isset($json['error']))
            {
                throw new \Exception($json['error']['message']);
            }

            return $json;
        }
        catch (\Exception $exception)
        {
            self::logApiStatus($this->action, $exception->getMessage(), 0);

            return [];
        }
    }

    protected static function logApiStatus(string $action, string $message, int $status)
    {
        global $db;

        $db->insert_query("rt_chatgpt_logs", [
            'message' => $db->escape_string($message),
            'action' => $db->escape_string($action),
            'status' => $status,
            'dateline' => TIME_NOW,
        ]);
    }

    protected abstract function getResponse();
}