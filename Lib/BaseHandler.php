<?php
namespace HaleyLeoZhang\Lib;

trait BaseHandler
{
    /**
     * 解析传入参数
     * @return array
     */
    public function parse_cli($argvs)
    {
        $arr = [];
        foreach ($argvs as $arg) {
            if (preg_match('/--(\w+)=(\w+)/', $arg, $match)) {
                $key       = $match[1];
                $value     = $match[2];
                $arr[$key] = $value;
            } else {
                echo '有错误参数传入  ' . $arg . PHP_EOL;
                echo PHP_EOL;
                echo '  --queue_name=队列名   默认队列名是 default' . PHP_EOL;
                echo '  --sleep=秒数     无任务时，进程挂起时长' . PHP_EOL;
                echo '  --times=次数     进程任务允许失败次数' . PHP_EOL;
                echo '  --timeout=秒数   任务超时时间' . PHP_EOL;
                echo '  --when_failed=记录方式' . PHP_EOL;
                echo '        log -> 存到日志文件中  ,  table -> 存到表中 ' . PHP_EOL;
                echo '        retry -> 重新尝试（这种可以能会导致redis消息堆积）, abandon->丢弃 ' . PHP_EOL;
                // var_dump($argvs);
                exit();
            }
        }

        return $arr;
    }

    /**
     * 处理延迟数据，并且保存
     * @return void
     */
    public function action_delay()
    {
        $time = $this->current_time;

        $queue_info = Config::get_queue_name($this->queue_name);
        $conn       = Config::get_redis($this->queue_name);
        $jobs       = $conn->zRangeByScore($queue_info['delay'], "-inf", $time);
        $jobs_len   = count($jobs);

        Log::debug("1.处理 {$queue_info['delay']} 队列.数量." . $jobs_len);

        if ($jobs_len > 0) {
            $max_index = $jobs_len - 1;
            $conn->zRemRangeByRank($queue_info['delay'], 0, $max_index);
            foreach ($jobs as $job) {
                $conn->rpush($queue_info['job'], $job);
            }
        }
        Log::debug('1.处理延迟数据，推送到 job 队列.数量.' . $jobs_len);
        // 先推送到 Delay 结构中去
    }

    /**
     * 拉任务队列数据
     * @return void
     */
    public function action_consume()
    {
        $queue_info = Config::get_queue_name($this->queue_name);
        $conn       = Config::get_redis($this->queue_name);
        $job        = $conn->lPop($queue_info['job']);
        if ($job) {
            $this->action_reserved($this->queue_name, $job, $this->task_info);
            $job_info = Parser::unpack($job);
            $job_obj  = unserialize($job_info['data']);
            $job_obj->handle();
            $this->action_notify($this->queue_name, $job);
            Log::debug('2.0 数据处理完成  ' . $job);
        } else {
            $this->task_info['sleep'] = $this->task_info['sleep'] ?? 3;
            Log::debug('2.1 数据接收完成，暂无数据，进程挂起指定秒数  ' . $this->task_info['sleep']);
            sleep($this->task_info['sleep']);
            Log::debug('2.2 进程已唤醒');
        }
    }

    /**
     * 待确认执行成功的任务
     * @param string $job 任务数据
     * @return void
     */
    public function action_reserved($job)
    {
        $queue_info                 = Config::get_queue_name($this->queue_name);
        $conn                       = Config::get_redis($this->queue_name);
        $this->task_info['timeout'] = $this->task_info['timeout'] ?? 30;
        $retry_time                 = $this->current_time + $this->task_info['timeout'];
        $conn->zAdd($queue_info['reserved'], $retry_time, $job);
    }

    /**
     * 通知任务处理完成
     * @param string $job 队列数据
     * @return void
     */
    public function action_notify($job)
    {
        $queue_info = Config::get_queue_name($this->queue_name);
        $conn       = Config::get_redis($this->queue_name);
        $conn->zRem($queue_info['reserved'], $job);
        Log::debug('3.数据确认完成，清除数据 reserved 队列.数据  ' . $job);
    }

    /**
     * 判断任务是否超时，是否需要重试
     * @return void
     */
    public function judge_timeout()
    {
        extract($this->task_info);
        // 默认重试次数
        $times = $this->task_info['times'] ?? 3;
        // log -> 存到日志文件中  ,  table -> 存到表中 , retry -> 重新尝试（这种可以能会导致redis消息堆积）, abandon->丢弃
        $when_failed = $this->task_info['when_failed'] ?? 'log';

        $time       = $this->current_time;
        $queue_info = Config::get_queue_name($this->queue_name);
        $conn       = Config::get_redis($this->queue_name);
        $jobs       = $conn->zRangeByScore($queue_info['reserved'], "-inf", $time);
        // var_dump($jobs);
        // exit();

        $jobs_len = count($jobs);
        if ($jobs_len > 0) {
            $max_index = $jobs_len - 1;
            $conn->zRemRangeByRank($queue_info['reserved'], 0, $max_index);
            foreach ($jobs as $job) {
                $job_info = Parser::unpack($job);
                // 重试达到上限
                if ($job_info['attemps'] >= $times) {
                    switch ($when_failed) {
                        case 'table':
                            Log::error("队列 {$this->queue_name} 失败数据存储到表里---暂未开发   " . $job);
                            break;
                        case 'retry':
                            $job_info['attemps'] = 0;
                            $job                 = Parser::pack($job_info);
                            $conn->rpush($queue_info['job'], $job);
                            break;
                        case 'abandon':
                            Log::info("队列 {$this->queue_name} 已丢弃数据   " . $job);
                            break;
                        default:
                            Log::error("队列 {$this->queue_name} 任务失败次数达到上限   " . $job);
                    }
                    continue;
                }
                // 重新插入队列
                $job_info['attemps']++;
                $job = Parser::pack($job_info);
                $conn->rpush($queue_info['job'], $job);
            }
        }

        Log::debug('4.处理延迟数据，推送到 job 队列.数量.' . $jobs_len);
    }

}
