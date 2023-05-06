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

	public function __construct(string $message)
	{
		parent::__construct();

		$this->action = 'OpenAI Assistant - Thread moderation';
		$this->method = 'POST';
		$this->input = $message;

		$this->response = $this->sendRequest($this->url, $message);
	}

	public function getResponse(): array
	{
		// Work in progress
		return [];
	}
}