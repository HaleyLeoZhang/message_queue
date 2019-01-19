<?php
require_once __DIR__ . '/Lib/Load.php'; // 自动加载
use HaleyLeoZhang\Lib\Listener;

array_shift($argv); // 命令行参数
$listen = new Listener($argv);
$listen->start();