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
        $this->validateAuthContext($usercode, $cookie);
    }

    /**
     * getUserConf接口
     * @return array
     */
    public function getUserConf(): array
    {
        $url = '/personalInfo/common/getUserConf';
        $response = $this->httpRequest('GET', $url, '', $this->cookieString, $this->jsonHeaders());
        return $this->decodeJsonData($response);
    }

    /**
     * 获取账号设置
     * @return array
     */
    public function accountSetting(): array
    {
        $url = '/personalInfo/accountSecurity/accountSetting';
        $result = $this->httpRequest('POST', $url, '', $this->cookieString, $this->jsonHeaders());
        return $this->decodeJsonData($result);
    }
}
