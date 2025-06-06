/**
 * VIP视频解析前端处理模块
 * 支持自动检测VIP链接、解析进度显示、错误处理等功能
 */
var VipParser = {
    // 配置选项
    config: {
        api_url: '../cj/dplayer/',
        timeout: 30000,
        retry_count: 2,
        show_progress: true
    },
    
    // VIP网站域名列表
    vip_domains: [
        'v.qq.com',
        'www.iqiyi.com', 
        'v.youku.com',
        'www.mgtv.com',
        'tv.sohu.com',
        'www.le.com',
        'www.bilibili.com'
    ],
    
    // 初始化解析器
    init: function(options) {
        if (options) {
            $.extend(this.config, options);
        }
        
        this.log('info', 'VIP解析器初始化完成');
    },
    
    /**
     * 检查是否为VIP视频链接
     * @param {string} url 视频链接
     * @return {boolean} 是否为VIP链接
     */
    isVipUrl: function(url) {
        if (!url || typeof url !== 'string') {
            return false;
        }
        
        try {
            var urlObj = new URL(url);
            var hostname = urlObj.hostname.toLowerCase();
            
            return this.vip_domains.some(function(domain) {
                return hostname.indexOf(domain) !== -1;
            });
        } catch (e) {
            return false;
        }
    },
    
    /**
     * 解析VIP视频链接
     * @param {string} vip_url VIP视频链接
     * @param {object} callbacks 回调函数 {onProgress, onSuccess, onError}
     */
    parseVideo: function(vip_url, callbacks) {
        var self = this;
        callbacks = callbacks || {};
        
        // 验证输入
        if (!vip_url) {
            this.handleError('请提供视频链接', callbacks.onError);
            return;
        }
        
        if (!this.isVipUrl(vip_url)) {
            this.handleError('不是支持的VIP视频网站链接', callbacks.onError);
            return;
        }
        
        this.log('info', '开始解析VIP视频: ' + vip_url);
        
        // 显示解析进度
        if (this.config.show_progress && callbacks.onProgress) {
            callbacks.onProgress('正在解析视频链接...', 0);
        }
        
        // 发送解析请求
        this.sendParseRequest(vip_url, 0, callbacks);
    },
    
    /**
     * 发送解析请求
     * @param {string} vip_url VIP视频链接
     * @param {number} retry_count 重试次数
     * @param {object} callbacks 回调函数
     */
    sendParseRequest: function(vip_url, retry_count, callbacks) {
        var self = this;
        
        $.ajax({
            url: this.config.api_url,
            method: 'GET',
            data: {
                ac: 'parse',
                url: vip_url
            },
            timeout: this.config.timeout,
            dataType: 'json',
            success: function(response) {
                self.handleParseResponse(response, vip_url, retry_count, callbacks);
            },
            error: function(xhr, status, error) {
                self.handleRequestError(xhr, status, error, vip_url, retry_count, callbacks);
            }
        });
    },
    
    /**
     * 处理解析响应
     * @param {object} response 服务器响应
     * @param {string} vip_url 原始VIP链接
     * @param {number} retry_count 重试次数
     * @param {object} callbacks 回调函数
     */
    handleParseResponse: function(response, vip_url, retry_count, callbacks) {
        if (response.success && response.data && response.data.url) {
            this.log('info', '解析成功: ' + response.data.url);
            
            // 更新进度
            if (this.config.show_progress && callbacks.onProgress) {
                callbacks.onProgress('解析成功！', 100);
            }
            
            // 调用成功回调
            if (callbacks.onSuccess) {
                callbacks.onSuccess({
                    original_url: vip_url,
                    parsed_url: response.data.url,
                    title: response.data.title || '未知标题',
                    type: response.data.type || 'unknown',
                    message: response.message || '解析成功'
                });
            }
        } else {
            // 解析失败，尝试重试
            var error_msg = response.message || '解析失败';
            this.log('warning', '解析失败: ' + error_msg);
            
            if (retry_count < this.config.retry_count) {
                this.log('info', '准备重试解析 (' + (retry_count + 1) + '/' + this.config.retry_count + ')');
                
                if (this.config.show_progress && callbacks.onProgress) {
                    callbacks.onProgress('解析失败，正在重试...', 30 + retry_count * 20);
                }
                
                setTimeout(() => {
                    this.sendParseRequest(vip_url, retry_count + 1, callbacks);
                }, 2000);
            } else {
                this.handleError(error_msg, callbacks.onError);
            }
        }
    },
    
    /**
     * 处理请求错误
     * @param {object} xhr XMLHttpRequest对象
     * @param {string} status 状态
     * @param {string} error 错误信息
     * @param {string} vip_url 原始VIP链接
     * @param {number} retry_count 重试次数
     * @param {object} callbacks 回调函数
     */
    handleRequestError: function(xhr, status, error, vip_url, retry_count, callbacks) {
        var error_msg = '网络请求失败';
        
        if (status === 'timeout') {
            error_msg = '解析超时';
        } else if (status === 'error') {
            error_msg = '网络错误';
        } else if (status === 'abort') {
            error_msg = '请求被取消';
        }
        
        this.log('error', error_msg + ': ' + error);
        
        // 尝试重试
        if (retry_count < this.config.retry_count && status !== 'abort') {
            this.log('info', '网络错误，准备重试 (' + (retry_count + 1) + '/' + this.config.retry_count + ')');
            
            if (this.config.show_progress && callbacks.onProgress) {
                callbacks.onProgress('网络错误，正在重试...', 20 + retry_count * 15);
            }
            
            setTimeout(() => {
                this.sendParseRequest(vip_url, retry_count + 1, callbacks);
            }, 3000);
        } else {
            this.handleError(error_msg, callbacks.onError);
        }
    },
    
    /**
     * 处理错误
     * @param {string} message 错误消息
     * @param {function} onError 错误回调函数
     */
    handleError: function(message, onError) {
        this.log('error', message);
        
        if (onError && typeof onError === 'function') {
            onError(message);
        }
    },
    
    /**
     * 显示解析进度对话框
     * @param {string} vip_url VIP视频链接
     * @param {function} onSuccess 成功回调
     * @param {function} onError 错误回调
     */
    showParseDialog: function(vip_url, onSuccess, onError) {
        var self = this;
        
        // 创建进度对话框
        var dialog_html = `
            <div id="parse-dialog" style="text-align: center; padding: 20px;">
                <div style="margin-bottom: 15px;">
                    <i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop" style="font-size: 30px; color: #1E9FFF;"></i>
                </div>
                <div id="parse-message" style="margin-bottom: 15px; font-size: 14px;">正在解析视频链接...</div>
                <div id="parse-progress" style="margin-bottom: 10px;">
                    <div style="background: #f0f0f0; height: 6px; border-radius: 3px; overflow: hidden;">
                        <div id="parse-progress-bar" style="background: #1E9FFF; height: 100%; width: 0%; transition: width 0.3s;"></div>
                    </div>
                </div>
                <div style="font-size: 12px; color: #999;">请稍候，正在为您解析VIP视频...</div>
            </div>
        `;
        
        var dialog_index = layer.open({
            type: 1,
            title: 'VIP视频解析',
            content: dialog_html,
            area: ['400px', '200px'],
            closeBtn: 1,
            shadeClose: false,
            cancel: function() {
                // 用户取消解析
                self.log('info', '用户取消解析');
                return true;
            }
        });
        
        // 开始解析
        this.parseVideo(vip_url, {
            onProgress: function(message, progress) {
                $('#parse-message').text(message);
                $('#parse-progress-bar').css('width', progress + '%');
            },
            onSuccess: function(result) {
                layer.close(dialog_index);
                if (onSuccess) {
                    onSuccess(result);
                }
            },
            onError: function(error) {
                layer.close(dialog_index);
                layer.msg('解析失败: ' + error, {icon: 2});
                if (onError) {
                    onError(error);
                }
            }
        });
    },
    
    /**
     * 获取解析统计信息
     * @param {function} callback 回调函数
     */
    getStats: function(callback) {
        $.ajax({
            url: this.config.api_url,
            method: 'GET',
            data: {ac: 'parse_stats'},
            dataType: 'json',
            success: function(response) {
                if (callback) {
                    callback(response.data);
                }
            },
            error: function() {
                if (callback) {
                    callback(null);
                }
            }
        });
    },
    
    /**
     * 清理解析缓存
     * @param {function} callback 回调函数
     */
    clearCache: function(callback) {
        $.ajax({
            url: this.config.api_url,
            method: 'GET',
            data: {ac: 'clear_parse_cache'},
            dataType: 'json',
            success: function(response) {
                if (callback) {
                    callback(response.code === 1, response.message);
                }
            },
            error: function() {
                if (callback) {
                    callback(false, '网络错误');
                }
            }
        });
    },
    
    /**
     * 日志记录
     * @param {string} level 日志级别
     * @param {string} message 日志消息
     */
    log: function(level, message) {
        if (console && console.log) {
            var timestamp = new Date().toLocaleTimeString();
            console.log('[' + timestamp + '] [VipParser] [' + level + '] ' + message);
        }
    }
};

// 全局暴露VipParser对象
window.VipParser = VipParser;
