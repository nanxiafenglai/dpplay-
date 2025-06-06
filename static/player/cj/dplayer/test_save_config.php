<?php
/**
 * VIP解析配置保存功能测试脚本
 * 用于测试save_config API接口是否正常工作
 */

// 设置错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>VIP解析配置保存功能测试</h2>";

// 测试数据
$test_config = [
    'enable' => true,
    'timeout' => 30,
    'retry_count' => 3,
    'cache_time' => 3600,
    'log_level' => 'info',
    'apis' => [
        'https://jiexi.789jiexi.net:4433/?url=',
        'https://jx.jsonplayer.com/player/?url='
    ],
    'vip_domains' => [
        'v.qq.com',
        'www.iqiyi.com'
    ]
];

$request_data = [
    'config' => $test_config
];

echo "<h3>1. 测试数据</h3>";
echo "<pre>" . json_encode($request_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

echo "<h3>2. 模拟POST请求</h3>";

// 模拟POST请求
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['ac'] = 'save_config';

// 模拟POST数据
$json_data = json_encode($request_data);
echo "发送的JSON数据: " . $json_data . "<br><br>";

// 临时重定向输入
$temp_input = tmpfile();
fwrite($temp_input, $json_data);
rewind($temp_input);

// 备份原始输入
$original_input = 'php://input';

echo "<h3>3. 执行测试</h3>";

try {
    // 包含必要的文件
    require_once 'init.php';
    require_once 'class/parse.class.php';
    
    echo "✅ 文件加载成功<br>";
    
    // 检查VideoParser类
    if (class_exists('VideoParser')) {
        echo "✅ VideoParser类存在<br>";
        
        $parser = new VideoParser();
        echo "✅ VideoParser实例创建成功<br>";
        
        // 直接测试saveConfig方法
        $result = $parser->saveConfig($test_config);
        echo "✅ saveConfig方法执行完成<br>";
        
        echo "<h4>保存结果:</h4>";
        echo "<pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        
        if ($result['success']) {
            echo "<div style='color: green; font-weight: bold;'>✅ 配置保存成功！</div>";
        } else {
            echo "<div style='color: red; font-weight: bold;'>❌ 配置保存失败: " . $result['message'] . "</div>";
        }
        
    } else {
        echo "❌ VideoParser类不存在<br>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ 异常: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 清理临时文件
fclose($temp_input);

echo "<h3>4. 配置文件检查</h3>";

$config_file = __DIR__ . '/save/config.inc.php';
if (file_exists($config_file)) {
    echo "✅ 配置文件存在: " . $config_file . "<br>";
    echo "文件大小: " . filesize($config_file) . " 字节<br>";
    echo "最后修改时间: " . date('Y-m-d H:i:s', filemtime($config_file)) . "<br>";
    
    if (is_writable($config_file)) {
        echo "✅ 配置文件可写<br>";
    } else {
        echo "❌ 配置文件不可写<br>";
    }
} else {
    echo "❌ 配置文件不存在<br>";
}

echo "<h3>5. 目录权限检查</h3>";

$save_dir = __DIR__ . '/save/';
if (is_dir($save_dir)) {
    echo "✅ save目录存在<br>";
    if (is_writable($save_dir)) {
        echo "✅ save目录可写<br>";
    } else {
        echo "❌ save目录不可写<br>";
    }
} else {
    echo "❌ save目录不存在<br>";
}

echo "<h3>6. 使用说明</h3>";
echo "<p>如果测试失败，请检查：</p>";
echo "<ul>";
echo "<li>文件权限：确保save目录和config.inc.php文件可写</li>";
echo "<li>PHP版本：确保PHP版本支持所使用的功能</li>";
echo "<li>错误日志：查看服务器错误日志获取详细信息</li>";
echo "</ul>";

echo "<p><strong>测试完成后请删除此文件以确保安全！</strong></p>";
?>
