<?php
namespace HaleyLeoZhang\Lib;

abstract class Dispatch
{
    protected $payload; // 推送到队列的数据
    protected $delay_time; // 延迟时间
    protected $queue_name; // 当前任务队列名称

    /**
     * 接口，必须实现该接口
     */
    abstract public function handle();

    /**
     * 设置超时时间，单位秒
     * @param int $second 秒数
     * @return \Dispatch
     */
    public function set_delay($second)
    {
        $this->delay_time = $second;
        return $this;
    }

    /**
     * 推送指定队列任务
     */
    public function push_queue($queue_name = 'default')
    {
        Log::info('serialize  ' . serialize($this));

        $queue_info = Config::get_queue_name($queue_name);
        $config     = Config::get_config();
        extract($config);
        $conn = Config::get_redis();

        $pop_time = time() + $this->delay_time;

        $id      = strtoupper(Token::rand_str(32));
        $attemps = 0;
        $data    = serialize($this);

        $job_info = compact('id', 'attemps', 'data');

        $payload = Parser::pack($job_info);

        $conn->zAdd($queue_info['delay'], $pop_time, $payload);
        Log::debug("推送到 {$queue_info['delay']} 成功");
    }

}
