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
                $key   = $match[1];
                $value = $match[2];
                $arr  = [
                    $key => $value,
                ];
            } else {
                echo '有错误参数传入  '. $arg . PHP_EOL;
                var_dump($argvs);
                exit();
            }
        }
        
        return $arr;
    }

    /**
     * 处理延迟数据，并且保存
     * @return void
     */
    public function action_delay($queue_name)
    {
        $time = time();

        $queue_info = Config::get_queue_name($queue_name);
        $conn       = Config::get_redis($queue_name);
        $jobs       = $conn->zRangeByScore($queue_info['delay'], "-inf", $time);
        $jobs_len = count($jobs);
        if ($jobs_len > 0) {
            $max_index = $jobs_len - 1;
        }else{
            $max_index = 0;
        }
        $conn->zRemRangeByRank($queue_info['delay'], 0, $max_index);
        foreach ($jobs as $job) {
            $conn->rpush($queue_info['job'], $job);
        }
        Log::debug('1.处理延迟数据，推送到 job 队列.数量.' . $jobs_len);
        // 先推送到 Delay 结构中去
    }

    /**
     * 拉任务队列数据
     * @return string
     */
    public function action_consume($queue_name, $task_info)
    {
        $queue_info = Config::get_queue_name($queue_name);
        $conn       = Config::get_redis($queue_name);
        $job        = $conn->lPop($queue_info['job']);
        if( $job ){
            $job_info = Parser::unpack($job);
            $job_obj = unserialize($job_info['data']);
            $job_obj->handle();
            $this->action_notify($this->queue_name, $job);
            Log::debug('2.数据接收完成，清除数据 job 队列.数据  ' . $job);
        }else{
            $task_info['sleep'] = $task_info['sleep'] ?? 3;
            Log::debug('2.数据接收完成，暂无数据，进程挂起指定秒数  ' . $task_info['sleep']);
            sleep($task_info['sleep']);
            Log::debug('进程已唤醒');
        }
        return $job;
    }

    /**
     * 通知任务处理完成
     */
    public function action_notify($queue_name, $job)
    {
        $queue_info = Config::get_queue_name($queue_name);
        $conn       = Config::get_redis($queue_name);
        $conn->zRem($queue_info['reserved'], $job);
        Log::debug('3.数据确认完成，清除数据 reserved 队列.数据  ' . $job);
    }

    /**
     * 判断任务是否超时，是否需要重试
     * @param array $params 包含 queue_name,times,when_failed
     */
    public function judge_timeout($queue_name, $params)
    {
        extract($params);
        // 默认重试次数
        $times = $times ?? 3;
        // log -> 存到日志文件中  ,  table -> 存到表中 , retry -> 重新尝试（这种可以能会导致redis消息堆积）, abandon->丢弃
        $when_failed = $when_failed ?? 'log';

        $time       = time();
        $queue_info = Config::get_queue_name($queue_name);
        $conn       = Config::get_redis($queue_name);
        $jobs       = $conn->zRangeByScore($queue_info['reserved'], "-inf", $time);

        $jobs_len = count($jobs);
        if ($jobs_len > 0) {
            $max_index = $jobs_len - 1;
        }else{
            $max_index = 0;
        }


        $conn->zRemRangeByRank($queue_info['reserved'], 0, $max_index);
        foreach ($jobs as $job) {
            $job_info = Parse::unpack($job);
            // 重试达到上限
            if ($job_info['attemps'] >= $times) {
                switch ($when_failed) {
                    case 'table':
                        Log::error("队列 {$queue_name} 失败数据存储到表里---暂未开发   " . $job);
                        break;
                    case 'retry':
                        $job_info['attemps'] = 0;
                        $job                 = Parse::pack($job_info);
                        $conn->rpush($queue_info['job'], $job);
                        break;
                    case 'abandon':
                        Log::info("队列 {$queue_name} 已丢弃数据   " . $job);
                        break;
                    default:
                        Log::error("队列 {$queue_name} 任务失败次数达到上限   " . $job);
                }
                continue;
            }
            // 重新插入队列
            $job_info['attemps']++;
            $job = Parse::pack($job_info);
            $conn->rpush($queue_info['job'], $job);
        }
        Log::debug('4.处理延迟数据，推送到 job 队列.数量.' . $jobs_len);
    }

}
