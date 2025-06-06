<?php
/**
 * VIP视频解析类
 * 支持多接口轮换、缓存、日志记录等功能
 */
class VideoParser
{
    private $config;
    private $cache_dir;
    private $log_file;
    
    public function __construct()
    {
        global $_config;
        $this->config = $_config['解析接口'];
        $this->cache_dir = __DIR__ . '/../save/parse_cache/';
        $this->log_file = __DIR__ . '/../save/parse.log';
        
        // 创建缓存目录
        if (!is_dir($this->cache_dir)) {
            @mkdir($this->cache_dir, 0755, true);
        }
    }
    
    /**
     * 解析VIP视频链接
     * @param string $vip_url VIP视频链接
     * @return array 解析结果
     */
    public function parseVideo($vip_url)
    {
        // 检查解析功能是否启用
        if (!$this->config['enable']) {
            return $this->errorResponse('解析功能已禁用');
        }
        
        // 验证输入URL
        if (!$this->isValidUrl($vip_url)) {
            return $this->errorResponse('无效的视频链接');
        }
        
        // 检查是否为支持的VIP网站
        if (!$this->isSupportedVipSite($vip_url)) {
            return $this->errorResponse('不支持的视频网站');
        }
        
        // 检查缓存
        $cached_result = $this->getCache($vip_url);
        if ($cached_result) {
            $this->log('info', "缓存命中: $vip_url");
            return $this->successResponse($cached_result, '缓存解析成功');
        }
        
        // 开始解析
        $this->log('info', "开始解析: $vip_url");
        
        foreach ($this->config['apis'] as $index => $api_url) {
            $result = $this->tryParseWithApi($api_url, $vip_url, $index + 1);
            
            if ($result['success']) {
                // 缓存解析结果
                $this->setCache($vip_url, $result['data']);
                return $result;
            }
        }
        
        return $this->errorResponse('所有解析接口都失败了');
    }
    
    /**
     * 使用指定API尝试解析
     * @param string $api_url 解析API地址
     * @param string $vip_url VIP视频链接
     * @param int $api_index API索引
     * @return array 解析结果
     */
    private function tryParseWithApi($api_url, $vip_url, $api_index)
    {
        $retry_count = 0;
        $max_retries = $this->config['retry_count'];
        
        while ($retry_count < $max_retries) {
            try {
                $this->log('debug', "尝试解析 API{$api_index} (重试{$retry_count}): $api_url");
                
                $parse_url = $api_url . urlencode($vip_url);
                $response = $this->httpRequest($parse_url);
                
                if ($response === false) {
                    throw new Exception("HTTP请求失败");
                }
                
                // 解析响应内容
                $parsed_data = $this->parseResponse($response, $vip_url);
                
                if ($parsed_data) {
                    $this->log('info', "解析成功 API{$api_index}: $vip_url -> {$parsed_data['url']}");
                    return $this->successResponse($parsed_data, "API{$api_index}解析成功");
                }
                
                throw new Exception("解析响应失败");
                
            } catch (Exception $e) {
                $retry_count++;
                $this->log('warning', "API{$api_index} 解析失败 (重试{$retry_count}): " . $e->getMessage());
                
                if ($retry_count < $max_retries) {
                    sleep(1); // 重试前等待1秒
                }
            }
        }
        
        $this->log('error', "API{$api_index} 所有重试都失败: $api_url");
        return $this->errorResponse("API{$api_index}解析失败");
    }
    
    /**
     * 发送HTTP请求
     * @param string $url 请求URL
     * @return string|false 响应内容
     */
    private function httpRequest($url)
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->config['timeout'],
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'header' => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: zh-CN,zh;q=0.8,en-US;q=0.5,en;q=0.3',
                    'Accept-Encoding: gzip, deflate',
                    'Referer: https://www.baidu.com/',
                ]
            ]
        ]);
        
        return @file_get_contents($url, false, $context);
    }
    
    /**
     * 解析响应内容，提取视频直链
     * @param string $response HTTP响应内容
     * @param string $original_url 原始VIP链接
     * @return array|false 解析后的视频信息
     */
    private function parseResponse($response, $original_url)
    {
        // 尝试多种解析方式
        
        // 方式1：查找iframe中的视频链接
        if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $response, $matches)) {
            $iframe_url = $matches[1];
            if ($this->isVideoUrl($iframe_url)) {
                return [
                    'url' => $iframe_url,
                    'type' => 'iframe',
                    'title' => $this->extractTitle($response),
                    'original_url' => $original_url
                ];
            }
        }
        
        // 方式2：查找video标签中的src
        if (preg_match('/<video[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $response, $matches)) {
            return [
                'url' => $matches[1],
                'type' => 'video',
                'title' => $this->extractTitle($response),
                'original_url' => $original_url
            ];
        }
        
        // 方式3：查找JavaScript中的视频链接
        if (preg_match('/(?:url|src|video)["\']?\s*[:=]\s*["\']([^"\']+\.(?:mp4|m3u8|flv)(?:\?[^"\']*)?)["\']?/i', $response, $matches)) {
            return [
                'url' => $matches[1],
                'type' => 'direct',
                'title' => $this->extractTitle($response),
                'original_url' => $original_url
            ];
        }
        
        // 方式4：查找m3u8链接
        if (preg_match('/(https?:\/\/[^"\'\s]+\.m3u8(?:\?[^"\'\s]*)?)/i', $response, $matches)) {
            return [
                'url' => $matches[1],
                'type' => 'hls',
                'title' => $this->extractTitle($response),
                'original_url' => $original_url
            ];
        }
        
        return false;
    }
    
    /**
     * 提取视频标题
     * @param string $response HTTP响应内容
     * @return string 视频标题
     */
    private function extractTitle($response)
    {
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $response, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        return '未知标题';
    }
    
    /**
     * 检查是否为有效的视频URL
     * @param string $url URL地址
     * @return bool 是否为视频URL
     */
    private function isVideoUrl($url)
    {
        $video_extensions = ['mp4', 'm3u8', 'flv', 'avi', 'mkv', 'webm'];
        $parsed_url = parse_url($url);
        
        if (!$parsed_url || !isset($parsed_url['path'])) {
            return false;
        }
        
        $extension = strtolower(pathinfo($parsed_url['path'], PATHINFO_EXTENSION));
        return in_array($extension, $video_extensions) || strpos($url, 'm3u8') !== false;
    }
    
    /**
     * 验证URL是否有效
     * @param string $url URL地址
     * @return bool 是否有效
     */
    private function isValidUrl($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * 检查是否为支持的VIP网站
     * @param string $url URL地址
     * @return bool 是否支持
     */
    private function isSupportedVipSite($url)
    {
        $parsed_url = parse_url($url);
        if (!$parsed_url || !isset($parsed_url['host'])) {
            return false;
        }
        
        $host = strtolower($parsed_url['host']);
        
        foreach ($this->config['vip_domains'] as $domain) {
            if (strpos($host, $domain) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 获取缓存
     * @param string $vip_url VIP视频链接
     * @return array|false 缓存数据
     */
    private function getCache($vip_url)
    {
        $cache_key = md5($vip_url);
        $cache_file = $this->cache_dir . $cache_key . '.json';
        
        if (!file_exists($cache_file)) {
            return false;
        }
        
        $cache_data = json_decode(file_get_contents($cache_file), true);
        
        if (!$cache_data || time() - $cache_data['timestamp'] > $this->config['cache_time']) {
            @unlink($cache_file);
            return false;
        }
        
        return $cache_data['data'];
    }
    
    /**
     * 设置缓存
     * @param string $vip_url VIP视频链接
     * @param array $data 解析数据
     */
    private function setCache($vip_url, $data)
    {
        $cache_key = md5($vip_url);
        $cache_file = $this->cache_dir . $cache_key . '.json';
        
        $cache_data = [
            'timestamp' => time(),
            'data' => $data
        ];
        
        @file_put_contents($cache_file, json_encode($cache_data));
    }
    
    /**
     * 记录日志
     * @param string $level 日志级别
     * @param string $message 日志消息
     */
    private function log($level, $message)
    {
        $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];
        $config_level = $levels[$this->config['log_level']] ?? 1;
        
        if ($levels[$level] < $config_level) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        @file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * 成功响应
     * @param array $data 数据
     * @param string $message 消息
     * @return array 响应数组
     */
    private function successResponse($data, $message = '解析成功')
    {
        return [
            'success' => true,
            'code' => 1,
            'message' => $message,
            'data' => $data
        ];
    }
    
    /**
     * 错误响应
     * @param string $message 错误消息
     * @return array 响应数组
     */
    private function errorResponse($message)
    {
        $this->log('error', $message);
        return [
            'success' => false,
            'code' => -1,
            'message' => $message,
            'data' => null
        ];
    }
    
    /**
     * 获取解析统计信息
     * @return array 统计信息
     */
    public function getStats()
    {
        $cache_files = glob($this->cache_dir . '*.json');
        $cache_count = count($cache_files);
        
        $log_size = file_exists($this->log_file) ? filesize($this->log_file) : 0;
        
        return [
            'cache_count' => $cache_count,
            'log_size' => $log_size,
            'config' => $this->config
        ];
    }
    
    /**
     * 清理缓存
     * @return bool 是否成功
     */
    public function clearCache()
    {
        $cache_files = glob($this->cache_dir . '*.json');
        $cleared = 0;

        foreach ($cache_files as $file) {
            if (@unlink($file)) {
                $cleared++;
            }
        }

        $this->log('info', "清理缓存完成，删除 {$cleared} 个文件");
        return $cleared > 0;
    }

    /**
     * 保存配置到文件
     * @param array $config 配置数组
     * @return array 保存结果
     */
    public function saveConfig($config)
    {
        try {
            // 验证配置数据
            $validation_result = $this->validateConfig($config);
            if (!$validation_result['valid']) {
                return [
                    'success' => false,
                    'message' => '配置验证失败: ' . $validation_result['error']
                ];
            }

            // 备份当前配置
            $backup_result = $this->backupConfig();
            if (!$backup_result['success']) {
                $this->log('warning', '配置备份失败: ' . $backup_result['message']);
            }

            // 读取当前完整配置文件
            $config_file = __DIR__ . '/../save/config.inc.php';
            if (!file_exists($config_file)) {
                return [
                    'success' => false,
                    'message' => '配置文件不存在'
                ];
            }

            $current_config = include $config_file;
            if (!is_array($current_config)) {
                return [
                    'success' => false,
                    'message' => '配置文件格式错误'
                ];
            }

            // 更新解析接口配置
            $current_config['解析接口'] = $config;

            // 生成新的配置文件内容
            $config_content = "<?php \nreturn " . $this->arrayToPhpString($current_config) . ";\n";

            // 写入配置文件
            $write_result = @file_put_contents($config_file, $config_content, LOCK_EX);

            if ($write_result === false) {
                return [
                    'success' => false,
                    'message' => '配置文件写入失败，请检查文件权限'
                ];
            }

            // 更新内存中的配置
            $this->config = $config;

            $this->log('info', '配置保存成功');

            return [
                'success' => true,
                'message' => '配置保存成功'
            ];

        } catch (Exception $e) {
            $this->log('error', '保存配置时发生异常: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '保存配置时发生异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 验证配置数据有效性
     * @param array $config 配置数组
     * @return array 验证结果
     */
    private function validateConfig($config)
    {
        // 检查必需字段
        $required_fields = ['enable', 'timeout', 'retry_count', 'cache_time', 'log_level', 'apis', 'vip_domains'];

        foreach ($required_fields as $field) {
            if (!isset($config[$field])) {
                return [
                    'valid' => false,
                    'error' => "缺少必需字段: $field"
                ];
            }
        }

        // 验证数据类型和范围
        if (!is_bool($config['enable'])) {
            return [
                'valid' => false,
                'error' => 'enable字段必须是布尔值'
            ];
        }

        if (!is_int($config['timeout']) || $config['timeout'] < 5 || $config['timeout'] > 300) {
            return [
                'valid' => false,
                'error' => 'timeout字段必须是5-300之间的整数'
            ];
        }

        if (!is_int($config['retry_count']) || $config['retry_count'] < 0 || $config['retry_count'] > 10) {
            return [
                'valid' => false,
                'error' => 'retry_count字段必须是0-10之间的整数'
            ];
        }

        if (!is_int($config['cache_time']) || $config['cache_time'] < 0) {
            return [
                'valid' => false,
                'error' => 'cache_time字段必须是非负整数'
            ];
        }

        if (!in_array($config['log_level'], ['debug', 'info', 'warning', 'error'])) {
            return [
                'valid' => false,
                'error' => 'log_level字段必须是debug、info、warning或error之一'
            ];
        }

        if (!is_array($config['apis'])) {
            return [
                'valid' => false,
                'error' => 'apis字段必须是数组'
            ];
        }

        if (!is_array($config['vip_domains'])) {
            return [
                'valid' => false,
                'error' => 'vip_domains字段必须是数组'
            ];
        }

        // 验证API URL格式
        foreach ($config['apis'] as $api) {
            if (!is_string($api) || !filter_var($api, FILTER_VALIDATE_URL)) {
                return [
                    'valid' => false,
                    'error' => "无效的API URL: $api"
                ];
            }
        }

        return [
            'valid' => true,
            'error' => null
        ];
    }

    /**
     * 备份当前配置文件
     * @return array 备份结果
     */
    private function backupConfig()
    {
        try {
            $config_file = __DIR__ . '/../save/config.inc.php';
            $backup_file = __DIR__ . '/../save/config.inc.php.backup.' . date('Y-m-d-H-i-s');

            if (!file_exists($config_file)) {
                return [
                    'success' => false,
                    'message' => '原配置文件不存在'
                ];
            }

            $copy_result = @copy($config_file, $backup_file);

            if (!$copy_result) {
                return [
                    'success' => false,
                    'message' => '备份文件创建失败'
                ];
            }

            return [
                'success' => true,
                'message' => '配置备份成功'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '备份过程中发生异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 将数组转换为PHP代码字符串
     * @param array $array 要转换的数组
     * @param int $indent 缩进级别
     * @return string PHP代码字符串
     */
    private function arrayToPhpString($array, $indent = 1)
    {
        $result = "[\n";
        $indent_str = str_repeat('    ', $indent);

        foreach ($array as $key => $value) {
            $result .= $indent_str . "'" . addslashes($key) . "' => ";

            if (is_array($value)) {
                $result .= $this->arrayToPhpString($value, $indent + 1);
            } elseif (is_bool($value)) {
                $result .= $value ? 'true' : 'false';
            } elseif (is_int($value)) {
                $result .= $value;
            } elseif (is_string($value)) {
                $result .= "'" . addslashes($value) . "'";
            } else {
                $result .= "'" . addslashes((string)$value) . "'";
            }

            $result .= ",\n";
        }

        $result .= str_repeat('    ', $indent - 1) . "]";
        return $result;
    }
}
