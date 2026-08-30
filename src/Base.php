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

    /** @var array 请求 Referer */
    protected const REFERER = '/personalInfo/personCenter/index.html';

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
        $this->configPath = $path === '' ? $defaultPath : $path;
    }

    /**
     * 设置统一认证系统URL
     * @param string $url
     * @return void
     */
    public function setAuthsysUrl(string $url = 'https://authserver.tjustb.cn'): void
    {
        if (empty($url)) $url = 'https://authserver.tjustb.cn';
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
        if ($envVal !== false) return $envVal;

        // 2) 使用传入路径或已设置路径
        $configFile = $path !== '' ? $path : $this->configPath;
        if (!empty($configFile) && file_exists($configFile)) {
            $content = file_get_contents($configFile);
            if ($content !== false) {
                foreach ($this->parseEnvLines($content) as $k => $v) {
                    if ($k === $key) return $v !== '' ? $v : $default;
                }
            }
        }

        // 3) 默认值
        return $default;
    }

    /**
     * 解析 .env 文件内容为键值对
     * @param string $content
     * @return array<string, string>
     */
    private function parseEnvLines(string $content): array
    {
        $result = [];
        $lines = preg_split('/\r\n|\r|\n/', $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            $pos = strpos($line, '=');
            if ($pos === false) continue;
            $result[trim(substr($line, 0, $pos))] = trim(substr($line, $pos + 1));
        }
        return $result;
    }

    /**
     * HTTP请求
     * @param string $method 请求方式
     * @param string $url 请求URL
     * @param mixed $body 请求体
     * @param mixed $cookie Cookie
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
    ): array {
        $url = $this->normalizeUrl($url);

        $defaultHeaders = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.150 Safari/537.36',
            'Accept-Encoding: gzip, deflate',
            'Accept-Language: zh',
        ];
        $headers = array_merge($defaultHeaders, $headers);
        $headers = $this->appendCookieHeader($headers, $cookie);

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
     * 标准化请求URL
     * @param string $url
     * @return string
     */
    private function normalizeUrl(string $url): string
    {
        if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
            $url = $this->authsysUrl . (str_starts_with($url, '/') ? $url : "/{$url}");
        }
        $url = trim($url);

        // 统一将 http 协议升级为 https
        $httpBaseUrl = str_replace('https://', 'http://', $this->authsysUrl);
        if (str_starts_with($url, 'http://') && str_contains($url, $httpBaseUrl)) {
            $url = str_replace('http://', 'https://', $url);
        }
        return $url;
    }

    /**
     * 将 Cookie 附加到请求头
     * @param array $headers
     * @param mixed $cookie
     * @return array
     */
    private function appendCookieHeader(array $headers, mixed $cookie): array
    {
        if (is_string($cookie)) {
            $cookie = trim($cookie);
            if ($cookie !== '') {
                $headers[] = !str_starts_with($cookie, 'Cookie:') ? "Cookie: {$cookie}" : $cookie;
            }
        } elseif (is_array($cookie) && !empty($cookie)) {
            $headers[] = 'Cookie: ' . $this->getCookieString($cookie);
        }
        return $headers;
    }

    /**
     * 构建 JSON 请求头
     * @return array
     */
    protected function jsonHeaders(): array
    {
        return [
            "refererToken: {$this->cookieArray['REFERERCE_TOKEN']}",
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json',
            'Content-Type: application/json',
            "Referer: {$this->authsysUrl}" . self::REFERER
        ];
    }

    /**
     * 解析响应中的 JSON 数据（若可解析则转换）
     * @param array $response
     * @return array
     */
    protected function decodeJsonData(array $response): array
    {
        $decoded = json_decode($response['data'], true);
        if (is_array($decoded)) {
            $response['data'] = $decoded;
        }
        return $response;
    }

    /**
     * 验证登录态（cookie 与 usercode）
     * @throws Exception\Exception
     */
    protected function validateAuthContext(string $usercode, array $cookie): void
    {
        if (empty($cookie)) throw new Exception\Exception('cookie不得为空');
        if (empty($usercode)) throw new Exception\Exception('账号参数不得为空');
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
        return implode('; ', array_map(
            fn($key, $value) => $key . '=' . $value,
            array_keys($cookie),
            array_values($cookie)
        ));
    }

    /**
     * 解析Cookie字符串
     * @param string $cookieString Cookie字符串
     * @return array Cookie数组
     */
    public function parseCookieString(string $cookieString = ''): array
    {
        if (str_starts_with($cookieString, 'Cookie: ')) $cookieString = substr($cookieString, 8);

        $cookieArray = [];
        $cookiePairs = explode(';', $cookieString);
        foreach ($cookiePairs as $pair) {
            $pair = trim($pair);
            $pos = strpos($pair, '=');
            if ($pos === false) continue;
            $cookieArray[trim(substr($pair, 0, $pos))] = trim(substr($pair, $pos + 1));
        }
        $this->cookieArray = $cookieArray;
        $this->cookieString = $this->getCookieString($cookieArray);
        return $cookieArray;
    }

    /**
     * 解析Cookie数组
     * @param array $cookie Cookie数组
     * @return string Cookie字符串
     */
    public function parseCookieArray(array $cookie = []): string
    {
        $this->cookieArray = $cookie;
        $this->cookieString = $this->getCookieString($cookie);
        return $this->cookieString;
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
        return trim($nextUrl[1] ?? '');
    }
}
