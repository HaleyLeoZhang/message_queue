<?php
namespace HaleyLeoZhang\Lib;
use \Redis;

class Config
{
    static $config = null;

    static $conn = null;

    static $queue_info = null;

    public static function get_config()
    {
        $ini_path = __DIR__ . '/../global.ini';
        if (is_null(self::$config)) {
            self::$config = parse_ini_file($ini_path, true);
        }
        return self::$config;

    }

    /**
     * 获取各种队列名
     * @param array $queue_name
     * @return array
     */
    public static function get_queue_name($queue_name)
    {
        if (is_null(self::$queue_info)) {
            $delay            = 'queue:' . $queue_name . ':delay';
            $job              = 'queue:' . $queue_name . ':job';
            $reserved         = 'queue:' . $queue_name . ':reserved';
            self::$queue_info = compact('delay', 'job', 'reserved');
        }
        return self::$queue_info;
    }

    /**
     * 获取 Redis 连接
     * @return \Redis
     */
    public static function get_redis()
    {
        if (is_null(self::$conn)) {
            $config = self::get_config();
            extract($config);
            self::$conn = new Redis();
            self::$conn->connect($redis['host'], $redis['port']);
            self::$conn->auth($redis['password']);
            self::$conn->select($redis['db']);
        }
        return self::$conn;
    }

}
