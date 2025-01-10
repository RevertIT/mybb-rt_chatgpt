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

namespace rt\ChatGPT\Models\OpenAi;


class OpenAiModel extends OpenAiService
{
    public function __construct()
    {
        global $lang;

        $lang->load('rt_chatgpt');

        parent::__construct();
    }

    public function chatCompletionReplyToThread(string $message): string
    {
        global $lang;
        /*
         Reference: https://platform.openai.com/docs/api-reference/chat

         Response:
         {
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

        $this->api_timeout = 30;
        $this->maxTokens = 500;
        $this->action = 'OpenAI Assistant - Reply to thread';
        $this->method = 'POST';
        $this->model = $this->mybb->settings['rt_chatgpt_openai_model'];
        $this->temperature = 0;
        $this->top_p = 1;
        $this->frequency_penalty = 0.0;
        $this->presence_penalty = 0.0;
        $this->stop[] = "Q: ";
        $this->prompt = "I am a highly intelligent question answering bot. I will use MyBB BBCode to output the answer. If you ask me a question that is nonsense, trickery, or has no clear answer, I will respond with \"{$lang->rt_chatgpt_response_negative}\". Q: ";

        $url = 'https://api.openai.com/v1/chat/completions';

        try
        {
            $response = $this->api($url, $message);

            if (!isset($response['choices'][0]['message']['content']))
            {
                self::logApiStatus($this->action, 'Empty response', 0);
                throw new \Exception('Empty response');
            }
        }
        catch (\Exception $e)
        {
            self::logApiStatus($this->action, $e->getMessage(), 0);
            throw new \Exception($e->getMessage());
        }

        // log successful response
        self::logApiStatus($this->action, "Response from ai: {$response['choices'][0]['message']['content']}", 1);
        return (string) $response['choices'][0]['message']['content'];
    }

    public function moderation(string $message): array
    {
        /*
         * Ref. https://platform.openai.com/docs/api-reference/moderations
         {
              "id": "modr-AB8CjOTu2jiq12hp1AQPfeqFWaORR",
              "model": "text-moderation-007",
              "results": [
                {
                  "flagged": true,
                  "categories": {
                    "sexual": false,
                    "hate": false,
                    "harassment": true,
                    "self-harm": false,
                    "sexual/minors": false,
                    "hate/threatening": false,
                    "violence/graphic": false,
                    "self-harm/intent": false,
                    "self-harm/instructions": false,
                    "harassment/threatening": true,
                    "violence": true
                  },
                  "category_scores": {
                    "sexual": 0.000011726012417057063,
                    "hate": 0.22706663608551025,
                    "harassment": 0.5215635299682617,
                    "self-harm": 2.227119921371923e-6,
                    "sexual/minors": 7.107352217872176e-8,
                    "hate/threatening": 0.023547329008579254,
                    "violence/graphic": 0.00003391829886822961,
                    "self-harm/intent": 1.646940972932498e-6,
                    "self-harm/instructions": 1.1198755256458526e-9,
                    "harassment/threatening": 0.5694745779037476,
                    "violence": 0.9971134662628174
                  }
                }
              ]
            }
         */

        $this->action = 'OpenAI Assistant - Thread moderation';
        $this->method = 'POST';
        $url = 'https://api.openai.com/v1/moderations';

        try
        {
            $response = $this->api($url, $message);

            if (!isset($response['results']))
            {
                self::logApiStatus($this->action, 'Empty response', 0);
                throw new \Exception('Empty response');
            }
        }
        catch (\Exception $e)
        {
            self::logApiStatus($this->action, $e->getMessage(), 0);
            throw new \Exception($e->getMessage());
        }

        // Log successful api response
        $api_score = json_encode($response['results'][0]['category_scores']);
        self::logApiStatus($this->action, $api_score, 1, $response['id']);

        return $response;
    }
}