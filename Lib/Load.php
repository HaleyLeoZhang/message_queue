<?php

// require_once __DIR__.'/Lib/Load.php'; // 自动加载

spl_autoload_register(function ($class) {
    try {
        $temp = explode('\\', $class);
        array_shift($temp);
        $after = implode('/', $temp);
        $path  = __DIR__ . '/../' . $after;
        // echo 'path----' . $path . PHP_EOL . PHP_EOL;
        // include 'classes/' . $class . '.class.php';
        $real = realpath($path . '.php');
        // echo 'real  ' . $path . PHP_EOL;
        if ($real) {
            require_once $real;
        } else {
            throw new \Exception("类 {$class}.php 不存在");
        }
    } catch (\Exception $e) {
        echo PHP_EOL . PHP_EOL;
        echo '提示  ' . $e->getMessage() . PHP_EOL;
        echo '位置  ' . $e->getFile() . PHP_EOL;
        echo '行数  ' . $e->getLine() . PHP_EOL;
        echo PHP_EOL . PHP_EOL;
        exit();
    }

});
