<?php

namespace Airmole\TjustbAuthsys;

use Airmole\TjustbAuthsys\Exception\Exception;

class Setting extends Base
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
     * 获取账号设置
     * @return array
     */
    public function accountSetting(): array
    {
        $url = '/personalInfo/accountSecurity/accountSetting';
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
}