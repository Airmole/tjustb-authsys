<?php

namespace Airmole\TjustbAuthsys;

/**
 * Base
 * 公共通用的方法
 */
class Base
{
    /**
     * 状态码 成功
     */
    public const CODE_SUCCESS = 200;

    /**
     * 状态码 重定向
     */
    public const CODE_REDIRECT = 302;

    /**
     * @var string 统一认证系统URL域名
     */
    public string $authsysUrl = '';

    /**
     * @var string 配置文件路径
     */
    public string $configPath = '';

    /**
     * @var string 账号
     */
    public string $usercode = '';

    /**
     * @var string 可用cookie值（仅登录成功后赋值）
     */
    public string $cookieString = '';

    /**
     * @var array 可用cookie数组（方便按名称获取对应值，仅登录成功后赋值）
     */
    public array $cookieArray = [];

    public function __construct()
    {
        // 设置默认配置文件
        if (empty($this->configPath)) $this->setConfigPath();
        // 未配置教务URL 自动配置
        if (empty($this->authsysUrl)) $this->setAuthsysUrl();
    }

    /**
     * 设置配置文件路径
     * @param string $path
     * @return void
     */
    public function setConfigPath(string $path = ''): void
    {
        // 优先使用环境变量 AUTHSYS_ENV 指定的 .env 路径，否则使用项目根目录 .env
        $defaultPath = getenv('AUTHSYS_ENV') ?: (dirname(__DIR__) . '/.env');
        if ($path === '') $path = $defaultPath;
        $this->configPath = $path;
    }

    /**
     * 设置统一认证系统URL
     * @param string $url
     * @return void
     */
    public function setAuthsysUrl(string $url = 'http://authserver.bkty.top'): void
    {
        if (empty($url)) $url = 'http://authserver.bkty.top';
        $this->authsysUrl = $this->getConfig('AUTHSYS_URL', $url);
    }

    /**
     * 获取配置项
     * @param string $key
     * @param $default
     * @param string $path
     * @return mixed|null
     */
    public function getConfig(string $key, $default = null, string $path = ''): mixed
    {
        // 1) 环境变量优先
        $envVal = getenv($key);
        if ($envVal !== false) {
            return $envVal;
        }

        // 2) 使用传入路径或已设置路径
        $configFile = $path !== '' ? $path : $this->configPath;
        if (!empty($configFile) && file_exists($configFile)) {
            $content = file_get_contents($configFile);
            if ($content !== false) {
                // 逐行解析 KEY=VALUE，忽略注释与空行
                $lines = preg_split('/\r\n|\r|\n/', $content);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) continue;
                    $pos = strpos($line, '=');
                    if ($pos === false) continue;
                    $k = trim(substr($line, 0, $pos));
                    $v = trim(substr($line, $pos + 1));
                    if ($k === $key) return $v !== '' ? $v : $default;
                }
            }
        }

        // 3) 默认值
        return $default;
    }

    /**
     * HTTP请求
     * @param string $method 请求方式
     * @param string $url 请求URL
     * @param mixed $body 请求体
     * @param mixed $cookie  Cookie
     * @param array $headers 请求头
     * @param bool $showHeaders 是否返回请求头
     * @param bool $followLocation 是否跟随跳转
     * @param int $timeout 超时时间
     * @return array 响应结果
     */
    public function httpRequest(
        string $method = 'GET',
        string $url = '',
        mixed  $body = '',
        mixed  $cookie = '',
        array  $headers = [],
        bool   $showHeaders = false,
        bool   $followLocation = false,
        int    $timeout = 5
    ): array
    {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = $this->authsysUrl . (str_starts_with($url, '/') ? $url : "/{$url}");
        }
        $url = trim($url);

        $defaultHeaders = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.150 Safari/537.36',
            'Accept-Encoding: gzip, deflate',
            'Accept-Language: zh',
        ];
        $headers = array_merge($defaultHeaders, $headers);

        if (is_string($cookie)) {
            $cookie = trim($cookie);
            $headers[] = !str_starts_with($cookie, 'Cookie:') ? "Cookie: {$cookie}" : $cookie;
        }
        if (is_array($cookie)) $headers[] = 'Cookie: ' . $this->getCookieString($cookie);

        $timeout = (int)$this->getConfig('AUTHSYS_TIMEOUT', $timeout);

        $requestOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => $followLocation,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => $showHeaders,
        ];

        if (!empty($body)) {
            $requestOptions[CURLOPT_POSTFIELDS] = is_array($body) ? http_build_query($body) : $body;
        }

        $ch = curl_init();
        curl_setopt_array($ch, $requestOptions);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['code' => 0, 'data' => 'cURL Error: ' . $error];
        }

        curl_close($ch);

        return ['code' => (int)$httpCode, 'data' => $response];
    }

    /**
     * 插入Cookie
     * @param string $key Cookie名称
     * @param string $value Cookie值
     * @return void
     */
    public function insertCookie(string $key, string $value): void
    {
        $this->cookieArray[$key] = $value;
        $this->cookieString = $this->getCookieString($this->cookieArray);
    }

    /**
     * 获取Cookie字符串
     * @param array $cookie Cookie数组
     * @return string Cookie字符串
     */
    public function getCookieString(array $cookie = []): string
    {
        if (empty($cookie)) $cookie = $this->cookieArray;
        $tempArray = [];
        foreach ($cookie as $key => $value) {
            $tempArray[] = $key . '=' . $value;
        }
        return implode('; ', $tempArray);
    }

    /**
     * 从响应头中获取Cookie
     * @param string $key Cookie名称
     * @param string $headerString 响应头字符串
     * @return string Cookie值
     */
    public function getCookieFromHeader(string $key, string $headerString = ''): string
    {
        preg_match("/Set-Cookie: {$key}=(.*?);/", $headerString, $cookieValue);
        return $cookieValue[1] ?? '';
    }

    /**
     * 从响应头中获取跳转地址
     * @param string $header 响应头字符串
     * @return string 跳转地址
     */
    public function getLocationFromRedirectHeader(string $header = ''): string
    {
        preg_match('/Location: (.*)/', $header, $nextUrl);
        return $nextUrl[1] ?? '';
    }
}