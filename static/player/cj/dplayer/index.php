<?php
// 强制清除OpCache缓存
if (function_exists('opcache_reset')) {
    opcache_reset();
}
if (function_exists('opcache_invalidate')) {
    opcache_invalidate(__FILE__, true);
}

require_once 'save/config.php';
require_once('init.php');
require_once('class/danmu.class.php');
require_once('class/parse.class.php');

if ($_GET['ac'] == "getdate") {
	$yzm = include ('save/data.php');
	if(strpos($yzm['yzm']['contextmenu'],chr(10)) !==false){
		$menu = explode(chr(10),$yzm['yzm']['contextmenu']);
		$contextmenu = [];
		foreach($menu as $v){
			if(strpos($v,',') !==false){
				$varr = explode(",",$v);
				$contextmenu[]=array(
					'text'=>$varr[0],
					'link'=>$varr[1],
				);
			}
		}
		if(!empty($contextmenu)){
			$yzm['yzm']['contextmenu'] = $contextmenu;
		}
	}else{
		if(strpos($yzm['yzm']['contextmenu'],',') !==false){
			$menu = explode(",",$yzm['yzm']['contextmenu']);
			$yzm['yzm']['contextmenu'] = array(array(
				'text'=>$menu[0],
				'link'=>$menu[1],
			));
		}
	}
	$user = !empty($_COOKIE['user_name'])?$_COOKIE['user_name']:'游客';
	$group_id = !empty($_COOKIE['group_id'])?$_COOKIE['group_id']:'';
	if(!empty($_COOKIE['user_id'])){
		$mysql = include '../../../../application/database.php';
		$db=new mysqli($mysql['hostname'],$mysql['username'],$mysql['password'],$mysql['database']);
		if(mysqli_connect_error()){

		}else{
			$result = $db->query("SELECT * FROM ".$mysql['prefix']."user WHERE user_id=" . $_COOKIE['user_id']);
			$row = $result->fetch_assoc();
			$group_id = $row['group_id'];
			$user = $row['user_name'];
		}
	}

	$yzm['yzm']['user'] = $user;
	$yzm['yzm']['group_id'] = $group_id;
    $json = [
       'code' => 1,
       'data' => $yzm['yzm']
    ];
	die(json_encode($json));
}

// VIP视频解析接口
if ($_GET['ac'] == "parse") {
    $vip_url = $_GET['url'] ?? '';

    if (empty($vip_url)) {
        $json = [
            'code' => -1,
            'message' => '请提供视频链接',
            'data' => null
        ];
        die(json_encode($json));
    }

    $parser = new VideoParser();
    $result = $parser->parseVideo($vip_url);

    die(json_encode($result));
}

// 解析统计接口
if ($_GET['ac'] == "parse_stats") {
    try {
        $parser = new VideoParser();
        $stats = $parser->getStats();

        $json = [
            'code' => 1,
            'message' => '获取统计信息成功',
            'data' => $stats
        ];
    } catch (Exception $e) {
        // 如果VideoParser初始化失败，返回默认配置
        $json = [
            'code' => 1,
            'message' => '使用默认配置',
            'data' => [
                'cache_count' => 0,
                'log_size' => 0,
                'config' => [
                    'enable' => false,
                    'timeout' => 30,
                    'retry_count' => 3,
                    'cache_time' => 3600,
                    'log_level' => 'info',
                    'apis' => [
                        'https://jiexi.789jiexi.net:4433/?url=',
                        'https://jx.jsonplayer.com/player/?url=',
                        'https://jx.parwix.com:4433/player/?url=',
                        'https://jx.blbo.cc:4433/?url='
                    ],
                    'vip_domains' => [
                        'v.qq.com',
                        'www.iqiyi.com',
                        'v.youku.com',
                        'www.mgtv.com',
                        'tv.sohu.com',
                        'www.le.com',
                        'www.bilibili.com'
                    ]
                ]
            ]
        ];
    }
    die(json_encode($json));
}

// 清理解析缓存接口
if ($_GET['ac'] == "clear_parse_cache") {
    $parser = new VideoParser();
    $result = $parser->clearCache();

    $json = [
        'code' => $result ? 1 : -1,
        'message' => $result ? '缓存清理成功' : '缓存清理失败',
        'data' => null
    ];
    die(json_encode($json));
}

// 简化测试接口
if (isset($_GET['ac']) && $_GET['ac'] == "test_save") {
    header('Content-Type: application/json');
    $json = [
        'code' => 1,
        'message' => '测试接口工作正常',
        'data' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'],
            'get_params' => $_GET
        ]
    ];
    die(json_encode($json));
}

// 保存解析配置接口 - 必须在其他处理逻辑之前
if (isset($_GET['ac']) && $_GET['ac'] == "save_config") {
    // 立即返回明确的响应，确保代码被执行
    header('Content-Type: application/json');

    // 立即输出一个明确的响应来确认代码被执行
    $debug_response = [
        'code' => 999,
        'message' => '进入save_config接口 - 代码正在执行',
        'debug' => [
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $_SERVER['REQUEST_URI'],
            'get_params' => $_GET
        ]
    ];

    // 记录调试信息
    error_log('[VIP解析配置保存] ===== 进入save_config接口 ===== ' . date('Y-m-d H:i:s'));
    error_log('[VIP解析配置保存] 接收到保存请求');
    error_log('[VIP解析配置保存] 请求方法: ' . $_SERVER['REQUEST_METHOD']);
    error_log('[VIP解析配置保存] 请求URI: ' . $_SERVER['REQUEST_URI']);
    error_log('[VIP解析配置保存] GET参数: ' . print_r($_GET, true));

    // 只允许POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $json = [
            'code' => -1,
            'message' => '只允许POST请求，当前请求方法: ' . $_SERVER['REQUEST_METHOD'],
            'data' => null
        ];
        error_log('[VIP解析配置保存] 错误: 请求方法不正确');
        die(json_encode($json));
    }

    // 获取POST数据
    $input = file_get_contents('php://input');
    error_log('[VIP解析配置保存] 接收到的原始数据: ' . $input);

    if (empty($input)) {
        $json = [
            'code' => -1,
            'message' => '请求数据为空',
            'data' => null
        ];
        error_log('[VIP解析配置保存] 错误: 请求数据为空');
        die(json_encode($json));
    }

    $config_data = json_decode($input, true);
    $json_error = json_last_error();

    if ($json_error !== JSON_ERROR_NONE) {
        $error_messages = [
            JSON_ERROR_DEPTH => '超过最大堆栈深度',
            JSON_ERROR_STATE_MISMATCH => '状态不匹配或无效JSON',
            JSON_ERROR_CTRL_CHAR => '控制字符错误',
            JSON_ERROR_SYNTAX => 'JSON语法错误',
            JSON_ERROR_UTF8 => 'UTF-8字符错误'
        ];

        $error_msg = isset($error_messages[$json_error]) ? $error_messages[$json_error] : '未知JSON错误';

        $json = [
            'code' => -1,
            'message' => 'JSON数据格式错误: ' . $error_msg,
            'data' => null
        ];
        error_log('[VIP解析配置保存] JSON解析错误: ' . $error_msg);
        die(json_encode($json));
    }

    error_log('[VIP解析配置保存] 解析后的数据: ' . print_r($config_data, true));

    if (!isset($config_data['config']) || !is_array($config_data['config'])) {
        $json = [
            'code' => -1,
            'message' => '缺少配置数据或配置数据格式错误',
            'data' => [
                'received_keys' => array_keys($config_data),
                'config_type' => isset($config_data['config']) ? gettype($config_data['config']) : 'not_set'
            ]
        ];
        error_log('[VIP解析配置保存] 错误: 配置数据格式错误');
        die(json_encode($json));
    }

    try {
        error_log('[VIP解析配置保存] 开始创建VideoParser实例');

        // 检查VideoParser类是否存在
        if (!class_exists('VideoParser')) {
            $json = [
                'code' => -1,
                'message' => 'VideoParser类不存在，请检查parse.class.php文件',
                'data' => null
            ];
            error_log('[VIP解析配置保存] 错误: VideoParser类不存在');
            die(json_encode($json));
        }

        $parser = new VideoParser();
        error_log('[VIP解析配置保存] VideoParser实例创建成功');

        $result = $parser->saveConfig($config_data['config']);
        error_log('[VIP解析配置保存] saveConfig方法执行结果: ' . print_r($result, true));

        $json = [
            'code' => $result['success'] ? 1 : -1,
            'message' => $result['message'],
            'data' => $result['success'] ? $config_data['config'] : null
        ];

        error_log('[VIP解析配置保存] 最终响应: ' . json_encode($json));

    } catch (Exception $e) {
        $error_msg = '保存配置时发生异常: ' . $e->getMessage();
        $json = [
            'code' => -1,
            'message' => $error_msg,
            'data' => [
                'exception_file' => $e->getFile(),
                'exception_line' => $e->getLine(),
                'exception_trace' => $e->getTraceAsString()
            ]
        ];
        error_log('[VIP解析配置保存] 异常: ' . $error_msg);
        error_log('[VIP解析配置保存] 异常详情: ' . $e->getTraceAsString());
    }

    // 如果到达这里，说明没有错误，返回正常响应
    if (!isset($json)) {
        $json = $debug_response; // 使用调试响应
    }

    die(json_encode($json));
}

$d = new danmu();
if ($_GET['ac'] == "edit") {
    $cid = $_POST['cid'] ?: showmessage(-1, null);
    $data = $d->编辑弹幕($cid) ?:  succeedmsg(0, '完成');
    exit;
}

// 重要：POST请求的通用处理必须排除save_config接口
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_GET['ac']) || $_GET['ac'] !== 'save_config')) {
    $d_data = json_decode(file_get_contents('php://input'), true);
    // 限制发送频率
    $lock = 1;
    $ip = get_ip();
    $data = sql::查询_发送弹幕次数($ip);

    if (empty($data)) {
        sql::插入_发送弹幕次数($ip);
        $lock = 0;
    } else {
        $data = $data[0];

        if ($data['time'] + $_config['限制时间'] > time()) {
            if ($data['c'] < $_config['限制次数']) {
                $lock = 0;
                sql::更新_发送弹幕次数($ip);
            };
        }

        if ($data['time'] + $_config['限制时间'] < time()) {
            sql::更新_发送弹幕次数($ip, time());
            $lock = 0;
        }
    }

    if ($lock === 0) {
        $d->添加弹幕($d_data);
        succeedmsg(23, true);
    } else {
        succeedmsg(-2, "发送的太频繁了");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($_GET['ac'] == "report") {
        $text = $_GET['text'];
        sql::举报_弹幕($text);
        showmessage(-3, '举报成功！感谢您为守护弹幕作出了贡献');
    } else if ($_GET['ac'] == "dm" or $_GET['ac'] == "get") {
        $id = $_GET['id'] ?: showmessage(-1, null);
        $data = $d->弹幕池($id) ?: showmessage(23, array());
        showmessage(23, $data);
    } else if ($_GET['ac'] == "list") {
        $data = $d->弹幕列表() ?: showmessage(0, []);
        showmessage(0, $data);
    } else if ($_GET['ac'] == "reportlist") {
        $data = $d->举报列表() ?: showmessage(0, []);     
        showmessage(0, $data);
    } else if ($_GET['ac'] == "del") {
        $id = $_GET['id'] ?: succeedmsg(-1, null);
        $type = $_GET['type'] ?: succeedmsg(-1, null);
        $data = $d->删除弹幕($id) ?: succeedmsg(0, []);
        succeedmsg(23, true);
    } else if ($_GET['ac'] == "so") {
        $key = $_GET['key'] ?: showmessage(0, null);
        $data = $d->搜索弹幕($key) ?: showmessage(0, []);
        showmessage(0, $data);
    }
}
