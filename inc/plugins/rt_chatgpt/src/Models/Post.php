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

class Post extends AbstractModel
{
    private array $response;
    private string $url = 'https://api.openai.com/v1/completions';

    public function __construct(string $message)
    {
        parent::__construct();

		$this->action = 'OpenAI Assistant - Reply to thread';
		$this->method = 'POST';
		$this->temperature = '0';
		$this->top_p = '1';
		$this->frequency_penalty = '0.0';
		$this->presence_penalty = '0.0';
		$this->maxTokens = 100;
		$this->prompt = "I am a highly intelligent question answering bot. If you ask me a question that is rooted in truth, I will give you the answer. I will use MyBB bbcode format to output the message when needed. If you ask me a question that is nonsense, trickery, or has no clear answer, I will respond with \"Unknown\".\n\nQ:";

		$this->response = $this->sendRequest($this->url, $message);
    }

	public function getResponse(): string
	{
		if (!isset($this->response['choices'][0]['text']))
		{
			return '';
		}

		// Log successful api response
		$data = "OpenAI ID: {$this->response['id']}, Model: {$this->response['model']}, Total tokens: {$this->response['usage']['total_tokens']}, Response: {$this->response['choices'][0]['text']}";
		self::logApiStatus($this->action, $data, 1);

		return $this->response['choices'][0]['text'];
	}

}