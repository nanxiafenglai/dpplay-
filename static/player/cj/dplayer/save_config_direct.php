<?php
/**
 * 独立的配置保存接口
 * 绕过可能的缓存和拦截问题
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 记录调试信息
error_log('[独立保存接口] 接收到请求: ' . date('Y-m-d H:i:s'));
error_log('[独立保存接口] 请求方法: ' . $_SERVER['REQUEST_METHOD']);
error_log('[独立保存接口] 请求URI: ' . $_SERVER['REQUEST_URI']);

try {
    // 只允许POST请求
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response = [
            'code' => -1,
            'message' => '只允许POST请求，当前请求方法: ' . $_SERVER['REQUEST_METHOD'],
            'data' => null
        ];
        echo json_encode($response);
        exit;
    }

    // 获取POST数据
    $input = file_get_contents('php://input');
    error_log('[独立保存接口] 接收到的原始数据: ' . $input);

    if (empty($input)) {
        $response = [
            'code' => -1,
            'message' => '请求数据为空',
            'data' => null
        ];
        echo json_encode($response);
        exit;
    }

    $config_data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $response = [
            'code' => -1,
            'message' => 'JSON数据格式错误: ' . json_last_error_msg(),
            'data' => null
        ];
        echo json_encode($response);
        exit;
    }

    error_log('[独立保存接口] 解析后的数据: ' . print_r($config_data, true));

    if (!isset($config_data['config']) || !is_array($config_data['config'])) {
        $response = [
            'code' => -1,
            'message' => '缺少配置数据或配置数据格式错误',
            'data' => [
                'received_keys' => array_keys($config_data),
                'config_type' => isset($config_data['config']) ? gettype($config_data['config']) : 'not_set'
            ]
        ];
        echo json_encode($response);
        exit;
    }

    // 加载必要的文件
    require_once 'class/parse.class.php';

    // 检查VideoParser类是否存在
    if (!class_exists('VideoParser')) {
        $response = [
            'code' => -1,
            'message' => 'VideoParser类不存在，请检查parse.class.php文件',
            'data' => null
        ];
        echo json_encode($response);
        exit;
    }

    $parser = new VideoParser();
    error_log('[独立保存接口] VideoParser实例创建成功');

    $result = $parser->saveConfig($config_data['config']);
    error_log('[独立保存接口] saveConfig方法执行结果: ' . print_r($result, true));

    $response = [
        'code' => $result['success'] ? 1 : -1,
        'message' => $result['message'],
        'data' => $result['success'] ? $config_data['config'] : null
    ];

    error_log('[独立保存接口] 最终响应: ' . json_encode($response));
    echo json_encode($response);

} catch (Exception $e) {
    $error_msg = '保存配置时发生异常: ' . $e->getMessage();
    $response = [
        'code' => -1,
        'message' => $error_msg,
        'data' => [
            'exception_file' => $e->getFile(),
            'exception_line' => $e->getLine(),
            'exception_trace' => $e->getTraceAsString()
        ]
    ];
    error_log('[独立保存接口] 异常: ' . $error_msg);
    echo json_encode($response);
}
?>
