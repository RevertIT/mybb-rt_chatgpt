<?php

function task_rt_chatgpt(array $task): void
{
    global $lang;

    $lang->load('rt_chatgpt');

    // Failcheck for the task in case the plugin is deleted, but task is still somehow active.
    if (class_exists('\rt\ChatGPT\Core'))
    {
        (new \rt\ChatGPT\Models\ThreadModel())->updateReplyWithAiResponse();
        (new \rt\ChatGPT\Models\ModerationModel())->moderateThread();
    }

    add_task_log($task, $lang->rt_chatgpt_task_ran);
}
