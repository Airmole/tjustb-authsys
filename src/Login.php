<?php

namespace Airmole\TjustbAuthsys;

use Airmole\TjustbAuthsys\Exception\Exception;

class Login extends Base
{
    /**
     * 获取登录参数
     * @throws Exception
     */
    public function loginPara(): array
    {
        $url = '/authserver/login';
        $html = $this->httpRequest('GET', $url, '', '', [
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Host: authserver.bkty.top',
            'sec-ch-ua: "Microsoft Edge";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "macOS"',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-User: ?1',
            'Sec-Fetch-Dest: document',
            'Accept-Encoding: gzip, deflate, br, zstd',
            'Accept-Language: zh-CN,zh;q=0.9'
        ], true);
        if ($html['code'] != self::CODE_SUCCESS) throw new Exception('系统响应异常：' . $html['data']);
        $response = $html['data'];
        $routeIdStr =  $this->getCookieFromHeader('route', $response);
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
     * @throws Exception
     */
    public function login(string $usercode, string $password, array $params = []): bool|array
    {
        if (empty($params['salt'])) throw new Exception('密码salt值不可为空');
        if (empty($params['execution'])) throw new Exception('execution值不可为空');
        // TODO 后续优化支持自动识别滑动验证码
        // if ($this->checkNeedCaptcha($usercode, $cookie)) throw new Exception('需要识别验证码');

        $generalHeaders = [
            'Connection: keep-alive',
            'Cache-Control: max-age=0',
            'Upgrade-Insecure-Requests: 1',
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Host: authserver.bkty.top',
            'sec-ch-ua: "Microsoft Edge";v="143", "Chromium";v="143", "Not A(Brand";v="24"',
            'sec-ch-ua-mobile: ?0',
            'sec-ch-ua-platform: "macOS"',
            'Sec-Fetch-Site: same-origin',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-User: ?1',
            'Sec-Fetch-Dest: document',
            'Accept-Encoding: gzip, deflate, br, zstd',
            'Accept-Language: zh-CN,zh;q=0.9'
        ];

        $url = '/authserver/login';
        $postData = [
            'username' => $usercode,
            'password' => $this->encryptPassword($password, $params['salt']),
            'captcha' => '',
            '_eventId' => $params['_eventId'],
            'cllt' => $params['cllt'],
            'dllt' => $params['dllt'],
            'lt' => $params['lt'],
            'execution' => $params['execution']
        ];
        $result = $this->httpRequest(
            'POST',
            $url,
            $postData,
            $params['cookie'],
            ['Content-Type: application/x-www-form-urlencoded'],
            true
        );
        if ($result['code'] != self::CODE_REDIRECT) {
            $validateResult = $this->validateLoginResult($result);
            if ($validateResult !== true) return $validateResult;
        }

        // 登录成功，记录原cookie
        $this->cookieArray = $params['cookie'];
        $this->cookieString = $this->getCookieString($this->cookieArray);
        $refererHeader = ["Referer: {$this->authsysUrl}/authserver/login"];
        // 获取 happyVoyage、CASTGC Cookie值并记录
        $happyVoyageCookieStr = $this->getCookieFromHeader('happyVoyage', $result['data']);
        if (!empty($happyVoyageCookieStr)) $this->insertCookie('happyVoyage', $happyVoyageCookieStr);

        $CASTGCCookieStr = $this->getCookieFromHeader('CASTGC', $result['data']);
        if (!empty($CASTGCCookieStr)) $this->insertCookie('CASTGC', $CASTGCCookieStr);
        $platformMultilingualCookieStr = $this->getCookieFromHeader('platformMultilingual', $result['data']);
        if (!empty($platformMultilingualCookieStr)) $this->insertCookie('platformMultilingual', $platformMultilingualCookieStr);
        $nextUrl = $this->getLocationFromRedirectHeader($result['data']);
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $result['data']);

        $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, array_merge($generalHeaders, $refererHeader), true);
        if ($redirect['code'] != self::CODE_REDIRECT)  throw new Exception('系统响应异常：' . $redirect['data']);
        $nextUrl = $this->getLocationFromRedirectHeader($redirect['data']);
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);

        $redirect = $this->httpRequest(
            'GET',
            $nextUrl,
            '',
            $this->cookieString,
            array_merge($generalHeaders, $refererHeader),
            true
        );
        if ($redirect['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $redirect['data']);
        $nextUrl = $this->getLocationFromRedirectHeader($redirect['data']);
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);

        $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, array_merge($generalHeaders, $refererHeader), true);
        if ($redirect['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $redirect['data']);
        $happyVoyageCookieStr = $this->getCookieFromHeader('happyVoyage', $result['data']);
        if (!empty($happyVoyageCookieStr)) $this->insertCookie('happyVoyage', $happyVoyageCookieStr);
        $nextUrl = $this->getLocationFromRedirectHeader($redirect['data']);
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);

        $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, array_merge($generalHeaders, $refererHeader), true);
        if ($redirect['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $redirect['data']);

        // 获取CAS Cookie
        $CASCookieStr = $this->getCookieFromHeader('MOD_AUTH_CAS', $redirect['data']);
        if (empty($CASCookieStr)) throw new Exception('CAS Cookie获取失败');
        $this->insertCookie('MOD_AUTH_CAS', $CASCookieStr);
        $nextUrl = $this->getLocationFromRedirectHeader($redirect['data']);
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);

        $redirect = $this->httpRequest('GET', $nextUrl, '', [
            'MOD_AUTH_CAS' => $CASCookieStr,
            'happyVoyage' => $happyVoyageCookieStr,
            'org.springframework.web.servlet.i18n.CookieLocaleResolver.LOCALE' => 'zh_CN',
            'platformMultilingual' => 'zh_CN'
        ], array_merge($generalHeaders, $refererHeader), true);
        if ($redirect['code'] != self::CODE_SUCCESS) throw new Exception('系统响应异常：' . $redirect['data']);

        $routeCookieStr = $this->getCookieFromHeader('route', $redirect['data']);
        if (!empty($routeCookieStr)) $this->insertCookie('route', $routeCookieStr);

        $jsessionIdCookieStr = $this->getCookieFromHeader('JSESSIONID', $redirect['data']);
        if (!empty($jsessionIdCookieStr)) $this->insertCookie('JSESSIONID', $jsessionIdCookieStr);

        $encryptKeyCookieStr = $this->getCookieFromHeader('WIS_PER_ENC', $redirect['data']);
        if (!empty($encryptKeyCookieStr)) $this->insertCookie('WIS_PER_ENC', $encryptKeyCookieStr);

        $refTokenCookieStr = $this->getCookieFromHeader('REFERERCE_TOKEN', $redirect['data']);
        if (!empty($refTokenCookieStr)) $this->insertCookie('REFERERCE_TOKEN', $refTokenCookieStr);
        $this->usercode = $usercode;
        return ['code' => 200, 'data' => 'success'];
    }

    /**
     * 注销登录
     * @return array
     * @throws Exception
     */
    public function logout(): array
    {
        $url = '/personalInfo/logout';
        $refererHeader = ["Referer: {$this->authsysUrl}/personalInfo/personCenter/index.html"];
        $response = $this->httpRequest('GET', $url, '', $this->cookieString, $refererHeader, true);
        if ($response['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $response['data']);
        $nextUrl = $this->getLocationFromRedirectHeader($response['data']);
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $response['data']);

        $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, $refererHeader, true);
        if ($redirect['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $redirect['data']);
        $nextUrl = $this->getLocationFromRedirectHeader($redirect['data']);
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);

        $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, $refererHeader, true);
        if ($redirect['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $redirect['data']);
        $nextUrl = $this->getLocationFromRedirectHeader($redirect['data']);
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);

        $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, $refererHeader, true);
        if ($redirect['code'] != self::CODE_SUCCESS) throw new Exception('系统响应异常：' . $redirect['data']);
        return ['code' => 200, 'data' => 'success'];
    }

    /**
     * 根据返回结果验证是否登录成功
     * @param array $response
     * @return array|true
     */
    public function validateLoginResult(array $response): bool|array
    {
        if (str_contains($response['data'], '该账号非常用账号或用户名密码有误')) return ['code' => 403, 'data' => '账号或密码错误'];
        if (str_contains($response['data'], '您提供的用户名或者密码有误')) return ['code' => 403, 'data' => '用户名或密码错误'];
        if (str_contains($response['data'], '登录凭证不可用')) return ['code' => 403, 'data' => '登录凭证不可用'];
        if (str_contains($response['data'], '图形动态码错误')) return ['code' => 403, 'data' => '失败次数过多，请手动登录教务网后再试'];
        if ($response['code'] == 502) return ['code' => 502, 'data' => '学校系统不稳定，请稍后再试'];
        return true;
    }

    /**
     * 密码加密
     * @param string $data
     * @param string $aesKey
     * @return string
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
     * 密码解密
     * @param string $data
     * @param string $aesKey
     * @return string
     */
    public function decryptPassword(string $data, string $aesKey): string
    {
        $iv = $this->randomString(16);
        $decrypted = openssl_decrypt(base64_decode($data), 'AES-128-CBC', $aesKey, OPENSSL_RAW_DATA, $iv);
        return substr($decrypted, 64);
    }

    /**
     * 生成随机字符串
     * @param int $length
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