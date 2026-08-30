<?php

namespace Airmole\TjustbAuthsys;

use Airmole\TjustbAuthsys\Exception\Exception;

class Login extends Base
{
    /** @var array 登录页访问请求头 */
    private const LOGIN_PAGE_HEADERS = [
        'Connection: keep-alive',
        'Upgrade-Insecure-Requests: 1',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Host: authserver.tjustb.cn',
        'sec-ch-ua: "Microsoft Edge";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "macOS"',
        'Sec-Fetch-Site: none',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-User: ?1',
        'Sec-Fetch-Dest: document',
        'Accept-Encoding: gzip, deflate, br, zstd',
        'Accept-Language: zh-CN,zh;q=0.9',
    ];

    /** @var array 登录后重定向请求头 */
    private const LOGIN_REDIRECT_HEADERS = [
        'Connection: keep-alive',
        'Cache-Control: max-age=0',
        'Upgrade-Insecure-Requests: 1',
        'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Host: authserver.tjustb.cn',
        'sec-ch-ua: "Microsoft Edge";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "macOS"',
        'Sec-Fetch-Site: same-origin',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-User: ?1',
        'Sec-Fetch-Dest: document',
        'Accept-Encoding: gzip, deflate, br, zstd',
        'Accept-Language: zh-CN,zh;q=0.9',
    ];

    /**
     * 获取登录参数
     * @param string $target 目标系统（兼容保留，暂未使用）
     * @throws Exception
     */
    public function loginPara(string $target = 'authsys'): array
    {
        $html = $this->httpRequest('GET', '/authserver/login', '', '', self::LOGIN_PAGE_HEADERS, true);
        if ($html['code'] != self::CODE_SUCCESS) throw new Exception('系统响应异常para：' . $html['data']);

        $response = $html['data'];
        $routeIdStr = $this->getCookieFromHeader('route', $response);
        $jsessionidStr = $this->getCookieFromHeader('JSESSIONID', $response);
        preg_match('/id="pwdEncryptSalt" value="(.*?)"/i', $response, $passwordSalt);
        preg_match('/name="execution" value="(.*?)"\/></i', $response, $execution);

        return [
            'cookie' => [
                'route' => $routeIdStr,
                'JSESSIONID' => $jsessionidStr,
                'org.springframework.web.servlet.i18n.CookieLocaleResolver.LOCALE' => 'zh_CN',
            ],
            '_eventId' => 'submit',
            'cllt' => 'userNameLogin',
            'dllt' => 'generalLogin',
            'lt' => '',
            'salt' => $passwordSalt[1] ?? '',
            'execution' => $execution[1] ?? '',
        ];
    }

    /**
     * 登录
     * @param string $usercode 账号
     * @param string $password 密码
     * @param array $params 登录参数
     * @param string $target 目标系统
     * @throws Exception
     */
    public function login(string $usercode, string $password, array $params = [], string $target = 'authsys'): bool|array
    {
        if (empty($params['salt'])) throw new Exception('密码salt值不可为空');
        if (empty($params['execution'])) throw new Exception('execution值不可为空');

        $postData = [
            'username' => $usercode,
            'password' => $this->encryptPassword($password, $params['salt']),
            'captcha' => '',
            '_eventId' => $params['_eventId'],
            'cllt' => $params['cllt'],
            'dllt' => $params['dllt'],
            'lt' => $params['lt'],
            'execution' => $params['execution'],
        ];

        $result = $this->httpRequest(
            'POST',
            '/authserver/login',
            $postData,
            $params['cookie'],
            ['Content-Type: application/x-www-form-urlencoded'],
            true
        );
        if ($result['code'] != self::CODE_REDIRECT) {
            $validateResult = $this->validateLoginResult($result);
            if ($validateResult !== true) return $validateResult;
        }

        // 登录成功，记录原 cookie
        $this->cookieArray = $params['cookie'];
        $this->cookieString = $this->getCookieString($this->cookieArray);

        $refererHeader = $target === 'authsys' ? ["Referer: {$this->authsysUrl}/authserver/login"] : [];
        $headers = array_merge(self::LOGIN_REDIRECT_HEADERS, $refererHeader);

        // 收集初次登录响应的 Cookie（happyVoyage、CASTGC、platformMultilingual 等）
        $this->collectCookies($result['data']);

        // 前3跳重定向
        $nextUrl = $this->getLocationFromRedirectHeader($result['data']);
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $result['data']);

        for ($i = 0; $i < 3; $i++) {
            $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, $headers, true);
            if ($redirect['code'] != self::CODE_REDIRECT) {
                throw new Exception('系统响应异常：' . $redirect['data']);
            }
            $nextUrl = $this->getLocationFromRedirectHeader($redirect['data']);
            if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);
        }

        // 第4跳（访问教务系统可能超时，超时视为登录成功）
        try {
            $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, $headers, true);
            if ($redirect['code'] != self::CODE_REDIRECT) {
                throw new Exception('系统响应异常：' . $redirect['data']);
            }
        } catch (\Exception $error) {
            if (str_contains($error, 'cURL Error: Operation timed out after')) {
                $this->usercode = $usercode;
                return ['code' => 200, 'data' => 'success'];
            }
            throw new Exception('系统响应异常：' . $error);
        }

        // 收集 MOD_AUTH_CAS 等 Cookie
        $this->collectCookies($redirect['data']);
        $nextUrl = $this->getLocationFromRedirectHeader($redirect['data']);
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);

        // 最终跳转（使用特定 Cookie 集合访问目标页面）
        $finalRedirect = $this->httpRequest('GET', $nextUrl, '', [
            'MOD_AUTH_CAS' => $this->cookieArray['MOD_AUTH_CAS'] ?? '',
            'happyVoyage' => $this->cookieArray['happyVoyage'] ?? '',
            'org.springframework.web.servlet.i18n.CookieLocaleResolver.LOCALE' => 'zh_CN',
            'platformMultilingual' => 'zh_CN',
        ], $headers, true);

        if ($finalRedirect['code'] != self::CODE_SUCCESS) {
            throw new Exception('系统响应异常：' . $finalRedirect['data']);
        }

        // 收集最终 Cookie（route、JSESSIONID、WIS_PER_ENC、REFERERCE_TOKEN 等）
        $this->collectCookies($finalRedirect['data']);

        $this->usercode = $usercode;
        return ['code' => 200, 'data' => 'success'];
    }

    /**
     * 从响应头中收集登录相关 Cookie
     * @param string $headerString 响应头字符串
     */
    private function collectCookies(string $headerString): void
    {
        $cookieKeys = [
            'happyVoyage', 'CASTGC', 'platformMultilingual',
            'route', 'JSESSIONID', 'WIS_PER_ENC', 'REFERERCE_TOKEN', 'MOD_AUTH_CAS',
        ];
        foreach ($cookieKeys as $key) {
            $value = $this->getCookieFromHeader($key, $headerString);
            if (!empty($value)) $this->insertCookie($key, $value);
        }
    }

    /**
     * 注销登录
     * @return array
     * @throws Exception
     */
    public function logout(): array
    {
        $refererHeader = ["Referer: {$this->authsysUrl}" . self::REFERER];
        $response = $this->httpRequest('GET', '/personalInfo/logout', '', $this->cookieString, $refererHeader, true);
        if ($response['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $response['data']);

        // 跟随注销重定向链
        for ($i = 0; $i < 5; $i++) {
            $nextUrl = $this->getLocationFromRedirectHeader($response['data']);
            if (empty($nextUrl)) throw new Exception('系统响应异常：' . $response['data']);

            $response = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, $refererHeader, true);
            if ($response['code'] == self::CODE_SUCCESS) return ['code' => 200, 'data' => 'success'];
            if ($response['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $response['data']);
        }
        throw new Exception('系统响应异常：重定向次数过多');
    }

    /**
     * 根据返回结果验证是否登录成功
     * @param array $response
     * @return array|true
     */
    public function validateLoginResult(array $response): bool|array
    {
        $errorMessages = [
            '该账号非常用账号或用户名密码有误' => ['code' => 403, 'data' => '账号或密码错误'],
            '您提供的用户名或者密码有误' => ['code' => 403, 'data' => '用户名或密码错误'],
            '登录凭证不可用' => ['code' => 403, 'data' => '登录凭证不可用'],
            '图形动态码错误' => ['code' => 403, 'data' => '失败次数过多，请手动登录教务网后再试'],
        ];
        foreach ($errorMessages as $pattern => $errorResult) {
            if (str_contains($response['data'], $pattern)) return $errorResult;
        }
        if ($response['code'] == 502) return ['code' => 502, 'data' => '学校系统不稳定，请稍后再试'];
        return true;
    }

    /**
     * 密码加密
     * @param string $data 明文密码
     * @param string $aesKey AES密钥
     * @return string 加密后的密码
     */
    public function encryptPassword(string $data, string $aesKey): string
    {
        if (!$aesKey) return $data;
        $aesKey = trim($aesKey);
        $randomString = $this->randomString(64);
        $iv = $this->randomString(16);
        $encrypted = openssl_encrypt($randomString . $data, 'AES-128-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encrypted);
    }

    /**
     * 生成随机字符串
     * @param int $length 长度
     * @return string
     */
    public function randomString(int $length): string
    {
        $characters = 'ABCDEFGHJKMNPQRSTWXYZabcdefhijkmnprstwxyz2345678';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
