<?php
/**
 * 验证文件更新状态
 */
header('Content-Type: application/json');

$index_file = __DIR__ . '/index.php';
$content = file_get_contents($index_file);

$has_save_config = strpos($content, 'save_config') !== false;
$has_test_save = strpos($content, 'test_save') !== false;
$has_debug_response = strpos($content, 'debug_response') !== false;

$response = [
    'code' => 1,
    'message' => '文件验证完成',
    'data' => [
        'file_exists' => file_exists($index_file),
        'file_size' => filesize($index_file),
        'last_modified' => date('Y-m-d H:i:s', filemtime($index_file)),
        'has_save_config' => $has_save_config,
        'has_test_save' => $has_test_save,
        'has_debug_response' => $has_debug_response,
        'file_writable' => is_writable($index_file),
        'timestamp' => date('Y-m-d H:i:s')
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT);
?>
