云天河用 `redis` 实现的消息队列  
需要安装 [Redis](https://github.com/phpredis/phpredis) 扩展  

开发原理 在[云天河博客](http://www.hlzblog.top/article/65.html) 中讲解    

示例运行方式

##### 配置日志存放路径

修改 [global.ini](./global.ini) 中的 `path` 变量  

    path=日志存放路径

##### 推送数据到队列

    php example_dispatch.php

##### 监听并消费队列

    php listen.php --queue_name=echo_log_job