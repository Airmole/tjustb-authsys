<?php

namespace Airmole\TjustbAuthsys;

use Airmole\TjustbAuthsys\Exception\Exception;

class Login extends Base
{
    /**
     * 获取登录参数
     * @throws Exception
     */
    public function getLoginPara(): array
    {
        $url = '/authserver/login';
        $html = $this->httpRequest('GET', $url, '', '', [], true);
        if ($html['code'] != self::CODE_SUCCESS) throw new Exception('系统响应异常：' . $html['data']);
        $response = $html['data'];
        preg_match('/Set-Cookie: route=(.*?); Path=\//i', $response, $routeId);
        preg_match('/Set-Cookie: JSESSIONID=(.*?); Path=\//i', $response, $jsessionId);
        $routeIdStr =  empty($routeId[1]) ? '' : $routeId[1];
        $jsessionidStr = empty($jsessionId[1]) ? '' : $jsessionId[1];
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
            'salt' => empty($passwordSalt[1]) ? '' : $passwordSalt[1],
            'execution' => empty($execution[1]) ? '' : $execution[1]
        ];
    }

    /**
     * @throws Exception
     */
    public function login(string $usercode, string $password, array $params = [])
    {
        if (empty($params['salt'])) throw new Exception('密码salt值不可为空');
        if (empty($params['execution'])) throw new Exception('execution值不可为空');
        // TODO 后续优化支持自动识别滑动验证码
        // if ($this->checkNeedCaptcha($usercode, $cookie)) throw new Exception('需要识别验证码');

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
        preg_match('/Set-Cookie: happyVoyage=(.*?);/', $result['data'], $happyVoyageCookie);
        $happyVoyageCookieStr = empty($happyVoyageCookie[1]) ? '' : $happyVoyageCookie[1];
        if (!empty($happyVoyageCookieStr)) $this->insertCookie('happyVoyage', $happyVoyageCookieStr);
        preg_match('/Set-Cookie: CASTGC=(.*?);/', $result['data'], $CASTGCCookie);
        $CASTGCCookieStr = empty($CASTGCCookie[1]) ? '' : $CASTGCCookie[1];
        if (!empty($CASTGCCookieStr)) $this->insertCookie('CASTGC', $CASTGCCookieStr);

        preg_match('/Location: (.*)/', $result['data'], $nextUrl);
        $nextUrl = empty($nextUrl[1]) ? '' : $nextUrl[1];
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $result['data']);
        $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, $refererHeader, true);
        if ($redirect['code'] != self::CODE_REDIRECT)  throw new Exception('系统响应异常：' . $redirect['data']);
        preg_match('/Location: (.*)/', $redirect['data'], $nextUrl);
        $nextUrl = empty($nextUrl[1]) ? '' : $nextUrl[1];
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);
        $redirect = $this->httpRequest(
            'GET',
            $nextUrl,
            '',
            ['org.springframework.web.servlet.i18n.CookieLocaleResolver.LOCALE' => 'zh_CN', 'happyVoyage' => $happyVoyageCookieStr],
            $refererHeader,
            true
        );
        if ($redirect['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $redirect['data']);
        preg_match('/Location: (.*)/', $redirect['data'], $nextUrl);
        $nextUrl = empty($nextUrl[1]) ? '' : $nextUrl[1];
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);

        $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, $refererHeader, true);
        if ($redirect['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $redirect['data']);
        preg_match('/Set-Cookie: happyVoyage=(.*?);/', $result['data'], $happyVoyageCookie);
        $happyVoyageCookieStr = empty($happyVoyageCookie[1]) ? '' : $happyVoyageCookie[1];
        if (!empty($happyVoyageCookieStr)) $this->insertCookie('happyVoyage', $happyVoyageCookieStr);
        preg_match('/Location: (.*)/', $redirect['data'], $nextUrl);
        $nextUrl = empty($nextUrl[1]) ? '' : $nextUrl[1];
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);
        $redirect = $this->httpRequest('GET', $nextUrl, '', [
            'org.springframework.web.servlet.i18n.CookieLocaleResolver.LOCALE' => 'zh_CN', 'happyVoyage' => $happyVoyageCookieStr
        ], $refererHeader, true);
        if ($redirect['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $redirect['data']);
        // 获取CAS Cookie
        preg_match('/Set-Cookie: MOD_AUTH_CAS=(.*?);/', $redirect['data'], $CASCookie);
        $CASCookieStr = empty($CASCookie[1]) ? '' : $CASCookie[1];
        if (empty($CASCookieStr)) throw new Exception('CAS Cookie获取失败');
        $this->insertCookie('MOD_AUTH_CAS', $CASCookieStr);
        preg_match('/Location: (.*)/', $redirect['data'], $nextUrl);
        $nextUrl = empty($nextUrl[1]) ? '' : $nextUrl[1];
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);
        $redirect = $this->httpRequest('GET', $nextUrl, '', [
            'org.springframework.web.servlet.i18n.CookieLocaleResolver.LOCALE' => 'zh_CN',
            'happyVoyage' => $happyVoyageCookieStr,
            'MOD_AUTH_CAS' => $CASCookieStr
        ], $refererHeader, true);
        if ($redirect['code'] != self::CODE_SUCCESS) throw new Exception('系统响应异常：' . $redirect['data']);
        preg_match('/Set-Cookie: route=(.*?);/', $redirect['data'], $routeCookie);
        $routeCookieStr = empty($routeCookie[1]) ? '' : $routeCookie[1];
        if (!empty($routeCookieStr)) $this->insertCookie('route', $routeCookieStr);
        preg_match('/Set-Cookie: JSESSIONID=(.*?);/', $redirect['data'], $jsessionIdCookie);
        $jsessionIdCookieStr = empty($jsessionIdCookie[1]) ? '' : $jsessionIdCookie[1];
        if (!empty($jsessionIdCookieStr)) $this->insertCookie('JSESSIONID', $jsessionIdCookieStr);
        preg_match('/Set-Cookie: EncryptKey=(.*?);/', $redirect['data'], $encryptKeyCookie);
        $encryptKeyCookieStr = empty($encryptKeyCookie[1]) ? '' : $encryptKeyCookie[1];
        if (!empty($encryptKeyCookieStr)) $this->insertCookie('EncryptKey', $encryptKeyCookieStr);
        preg_match('/Set-Cookie: REFERERCE_TOKEN=(.*?);/', $redirect['data'], $refTokenCookie);
        $refTokenCookieStr = empty($refTokenCookie[1]) ? '' : $refTokenCookie[1];
        if (!empty($refTokenCookieStr)) $this->insertCookie('REFERERCE_TOKEN', $refTokenCookieStr);
        $this->usercode = $usercode;
        return ['code' => 200, 'data' => 'success'];
    }

    /**
     * 登出
     * @return array
     * @throws Exception
     */
    public function logout(): array
    {
        $url = '/personalInfo/logout';
        $refererHeader = ["Referer: {$this->authsysUrl}/personalInfo/personCenter/index.html"];
        $response = $this->httpRequest('GET', $url, '', $this->cookieString, $refererHeader, true);
        if ($response['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $response['data']);
        preg_match('/Location: (.*)/', $response['data'], $nextUrl);
        $nextUrl = empty($nextUrl[1]) ? '' : $nextUrl[1];
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $response['data']);
        $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, $refererHeader, true);
        if ($redirect['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $redirect['data']);
        preg_match('/Location: (.*)/', $redirect['data'], $nextUrl);
        $nextUrl = empty($nextUrl[1]) ? '' : $nextUrl[1];
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);
        $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, $refererHeader, true);
        if ($redirect['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $redirect['data']);
        preg_match('/Location: (.*)/', $redirect['data'], $nextUrl);
        $nextUrl = empty($nextUrl[1]) ? '' : $nextUrl[1];
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $redirect['data']);
        $redirect = $this->httpRequest('GET', $nextUrl, '', $this->cookieString, $refererHeader, true);
        if ($redirect['code'] != self::CODE_SUCCESS) throw new Exception('系统响应异常：' . $redirect['data']);
        return ['code' => 200, 'data' => 'success'];
    }

    /**
     * getUserConf接口
     * @return array
     */
    public function getUserConf(): array
    {
        $url = '/personalInfo/common/getUserConf';
        $response = $this->httpRequest('GET', $url, '', $this->cookieString, [
            "refererToken: {$this->cookieArray['REFERERCE_TOKEN']}",
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json',
            'Content-Type: application/json',
            "Referer: {$this->authsysUrl}/personalInfo/personCenter/index.html"
        ]);
        // 尝试转换json
        if (json_decode($response['data'], true)) {
            $response['data'] = json_decode($response['data'], true);
        }
        return $response;
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
        return  true;
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