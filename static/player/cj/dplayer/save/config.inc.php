<?php 
return [
    '后台密码' => '',
    'tips' => [
        'time' => '6',
        'color' => '#fb7299',
        'text' => '请文明发送弹幕,不要相信博彩广告',
    ],
    '防窥' => '0',
    '数据库' => [            // 注意=>前后都有一个空格。务必保留，不然出错  
        '类型' => 'sqlite',   //支持类型：mysql,sqlite 
        '方式' => 'pdo',        // 无需更改，只支持pdo模式
        '地址' => 'dmku.db',           //数据库名地址，mysql可以设置为'localhost',sqlite设置为数据库文件名,保存在save目录
        '用户名' => '',                //数据库名用户名 
        '密码' => '',                 //数据库名密码 
        '名称' => '',                 //数据库名称
       '端口' => 3306,
    ],
    'is_cdn' => 0,  //是否用了cdn
    '限制时间' => 60, //单位s
    '限制次数' => 20, //在限制时间内可以发送多少条弹幕
    '允许url' => [],  //跨域  格式['https://abc.com','http://cba.com']   要加协议
    '解析接口' => [
        'enable' => true,                    // 是否启用解析功能
        'timeout' => 30,                     // 解析超时时间（秒）
        'retry_count' => 3,                  // 单个接口重试次数
        'cache_time' => 3600,                // 解析结果缓存时间（秒）
        'log_level' => 'info',               // 日志级别：debug, info, warning, error
        'apis' => [                          // 解析接口列表
            'https://jiexi.789jiexi.net:4433/?url=',
            'https://jx.jsonplayer.com/player/?url=',
            'https://jx.parwix.com:4433/player/?url=',
            'https://jx.blbo.cc:4433/?url=',
        ],
        'vip_domains' => [                   // 支持的VIP视频网站域名
            'v.qq.com',                      // 腾讯视频
            'www.iqiyi.com',                 // 爱奇艺
            'v.youku.com',                   // 优酷
            'www.mgtv.com',                  // 芒果TV
            'tv.sohu.com',                   // 搜狐视频
            'www.le.com',                    // 乐视
            'www.bilibili.com',              // 哔哩哔哩
        ]
    ],
    '安装' => 1,
];
