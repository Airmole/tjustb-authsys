<?php

namespace Airmole\TjustbAuthsys;

use Airmole\TjustbAuthsys\Exception\Exception;

class App extends Base
{
    /**
     * 初始化
     * @throws Exception
     */
    public function __construct(string $usercode = '', array $cookie = [])
    {
        parent::__construct();
        $this->usercode = $usercode;
        $this->cookieArray = $cookie;
        $this->cookieString = $this->getCookieString($cookie);

        if (empty($cookie)) throw new Exception('cookie不得为空');
        if (empty($usercode)) throw new Exception('账号参数不得为空');
    }

    /**
     * 获取在线应用列表
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     */
    public function appList(int $page = 1, int $pageSize = 100): array
    {
        $url = '/personalInfo/myAuthority/onlineApp/list';
        $body = json_encode([
            'content' => '',
            'wid' => '',
            'pageNumber' => $page,
            'pageSize' => $pageSize,
        ]);
        $headers = [
            "refererToken: {$this->cookieArray['REFERERCE_TOKEN']}",
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json',
            'Content-Type: application/json',
            "Referer: {$this->authsysUrl}/personalInfo/personCenter/index.html"
        ];
        $result = $this->httpRequest('POST', $url, $body, $this->cookieString, $headers);
        if ($result['code'] != self::CODE_SUCCESS) return $result;
        if (json_decode($result['data'], true)) {
            $result['data'] = json_decode($result['data'], true);
        }
        return $result;
    }

    /**
     * 获取应用名称列表
     * @return array
     */
    public function appNameList(): array
    {
        $url = '/personalInfo/UserLogs/user/querySelectAppName';
        $headers = [
            "refererToken: {$this->cookieArray['REFERERCE_TOKEN']}",
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json',
            'Content-Type: application/json',
            "Referer: {$this->authsysUrl}/personalInfo/personCenter/index.html"
        ];
        $result = $this->httpRequest('POST', $url, '', $this->cookieString, $headers);
        if ($result['code'] != self::CODE_SUCCESS) return $result;
        if (json_decode($result['data'], true)) {
            $result['data'] = json_decode($result['data'], true);
        }
        return $result;
    }

    /**
     * 统一认证访问其他系统
     * @param string $targetUrl 目标访问系统
     * @return array
     * @throws Exception
     */
    public function visitOtherSystem(string $targetUrl = ''): array
    {
        $url = '/authserver/login?service=' . urlencode($targetUrl);
        $result = $this->httpRequest('GET', $url, '', $this->cookieString, [
            "Referer: {$targetUrl}"
        ], true);
        if ($result['code'] != self::CODE_REDIRECT) throw new Exception('系统响应异常：' . $result['data']);

        $happyVoyageCookie = $this->getCookieFromHeader('happyVoyage', $result['data']);
        if (!empty($happyVoyageCookie)) $this->insertCookie('happyVoyage', $happyVoyageCookie);

        $nextUrl = $this->getLocationFromRedirectHeader($result['data']);
        if (empty($nextUrl)) throw new Exception('系统响应异常：' . $result['data']);

        preg_match('/\?ticket=(.*?)/', $nextUrl, $ticket);
        $ticket = $ticket ?? '';

        return [ 'url' => $nextUrl, 'ticket' => $ticket];
    }

}