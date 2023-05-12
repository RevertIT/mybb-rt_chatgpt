<?php

function task_rt_chatgpt(array $task): void
{
    global $lang;

    $lang->load('rt_chatgpt');

    if (class_exists('\rt\ChatGPT\Core'))
    {
        (new \rt\ChatGPT\Models\Post())->updateReplyWithAiResponse();
    }

    add_task_log($task, $lang->rt_chatgpt_task_ran);
}