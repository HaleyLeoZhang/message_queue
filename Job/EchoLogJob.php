<?php
namespace HaleyLeoZhang\Job;

use HaleyLeoZhang\Lib\Dispatch;
use HaleyLeoZhang\Lib\Log;

// 消费队列的逻辑
class EchoLogJob extends Dispatch
{
    const QUEUE_NAME = 'echo_log_job';

    public function __construct($job_json_data)
    {
        $this->object = json_decode($job_json_data);
    }

    public function handle()
    {
        Log::info('消费队列的逻辑处理中...'. $this->object->id);
    }

}
