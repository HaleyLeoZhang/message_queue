<?php
require_once __DIR__ . '/Lib/Load.php'; // 自动加载

use HaleyLeoZhang\Job\EchoLogJob;
use HaleyLeoZhang\Lib\Log;

function example()
{
    $info = [];
    $info = [
        'id'   => 1,
        'name' => 'HaleyLeoZhang',
        'time' => time(),
    ];
    $job = new EchoLogJob(json_encode($info));
    $job->set_delay(3);
    $job->push_queue(EchoLogJob::QUEUE_NAME);
}

example();
