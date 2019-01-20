<?php
namespace HaleyLeoZhang\Lib;

class Listener
{
    use BaseHandler;

    protected $task_info; // 任务信息
    protected $queue_name; // 队列名字
    protected $current_time; // 当前任务时间

    /**
     * $argv 参数如下
     * - sleep 队列空闲时，进程挂起秒数，默认3秒
     * - times 队列执行未收到确认消息后，允许的失败次数
     * - timeout 任务超时时间
     * - when_failed 记录失败的方式
     *     log -> 存到日志文件中  ,  table -> 存到表中
     *     retry -> 重新尝试（这种可以能会导致redis消息堆积）, abandon->丢弃
     */
    public function __construct($argvs)
    {
        $this->task_info  = $this->parse_cli($argvs);
        $this->queue_name = $this->task_info['queue_name'] ?? 'default';
    }

    public function start()
    {
        for (;;) {
            try {
                $this->current_time = time();
                $this->action_delay();
                $this->action_consume();
                $this->judge_timeout();
            } catch (\Exception $exception) {
                $message = '';
                $message .= '位置  ' . $exception->getMessage() . '   ';
                $message .= '提示  ' . $exception->getFile() . '   ';
                $message .= '行数  ' . $exception->getLine() . '   ';
                Log::error('队列处理异常. ' . $message);
            }
        }
    }

}
