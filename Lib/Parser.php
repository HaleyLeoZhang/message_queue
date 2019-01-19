<?php
namespace HaleyLeoZhang\Lib;

class Parser
{
    /**
     * 单个队列数据入队前整合
     * @param array $job_info  包含 id, $attemps, $data
     * @return array
     */
    public static function pack($job_info)
    {
        $info = [];
        $info['job'] = $job_info;
        return json_encode($info);
    }

    /**
     * 解析单个队列数据
     * @param array $queue_name
     * @return array
     */
    public static function unpack($job)
    {
        $info = json_decode($job, true);
        return $info['job'];
    }

}
