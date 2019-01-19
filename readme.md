云天河用 `redis` 实现的消息队列  
需要安装 [Redis](https://github.com/phpredis/phpredis) 扩展  

对应开发原理 云天河有空的时候 将会在博客中讲解    

示例运行方式

##### 推送数据到队列

    php example_dispatch.php

##### 监听并消费队列

    php listen.php --queue_name=echo_log_job