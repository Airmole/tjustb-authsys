<?php

namespace Airmole\TjustbAuthsys;

use Airmole\TjustbAuthsys\Exception\Exception;

class Log extends Base
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
     * 获取账号在线列表
     * @return array
     */
    public function onlineList(): array
    {
        $url = '/personalInfo/UserOnline/user/queryUserOnline';
        $headers = [
            "refererToken: {$this->cookieArray['REFERERCE_TOKEN']}",
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json',
            'Content-Type: application/json',
            "Referer: {$this->authsysUrl}/personalInfo/personCenter/index.html"
        ];
        $result = $this->httpRequest('GET', $url, '', $this->cookieString, $headers);
        if (json_decode($result['data'])) $result['data'] = json_decode($result['data'], true);
        return $result;
    }

    /**
     * 获取登录记录
     * @param string|null $startTime 开始时间
     * @param string|null $endTime 结束时间
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string $result 结果
     * @param string $loginLocation 登录地点
     * @param string $typeCode 类型
     * @param string $appName 应用名称
     * @return array
     */
    public function loginLogs(
        string $startTime = null,
        string $endTime = null,
        int $page = 1,
        int $pageSize = 10,
        string $result = '',
        string $loginLocation = '',
        string $typeCode = '',
        string $appName = ''
    ): array
    {
        $url = '/personalInfo/UserLogs/user/queryUserLogs';
        $data = json_encode([
            "operType" => 0,
            "startTime"=> $startTime,
            "endTime"=> $endTime,
            "pageIndex"=> $page,
            "pageSize"=> $pageSize,
            "result"=> $result,
            "loginLocation"=> $loginLocation,
            "typeCode"=> $typeCode,
            "appName"=> $appName
        ]);
        $headers = [
            "refererToken: {$this->cookieArray['REFERERCE_TOKEN']}",
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json',
            'Content-Type: application/json',
            "Referer: {$this->authsysUrl}/personalInfo/personCenter/index.html"
        ];
        $response = $this->httpRequest('POST', $url, $data, $this->cookieString, $headers);
        if (json_decode($response['data'])) $response['data'] = json_decode($response['data'], true);
        return $response;
    }

    /**
     * 获取访问应用记录
     * @param string|null $startTime 开始时间
     * @param string|null $endTime 结束时间
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param string $result 结果
     * @param string $typeCode 类型
     * @param string $appName 应用名称
     * @param string $appId 应用ID
     * @return array
     */
    public function accessAppLogs(
        string $startTime = null,
        string $endTime = null,
        int $page = 1,
        int $pageSize = 10,
        string $result = '',
        string $typeCode = '',
        string $appName = '',
        string $appId = ''
    ): array
    {
        $url = '/personalInfo/UserLogs/user/queryUserLogs';
        $data = json_encode([
            "operType" => 3,
            "startTime"=> $startTime,
            "endTime"=> $endTime,
            "pageIndex"=> $page,
            "pageSize"=> $pageSize,
            "result"=> $result,
            "typeCode"=> $typeCode,
            "appName"=> $appName,
            "appId" => $appId
        ]);
        $headers = [
            "refererToken: {$this->cookieArray['REFERERCE_TOKEN']}",
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json',
            'Content-Type: application/json',
            "Referer: {$this->authsysUrl}/personalInfo/personCenter/index.html"
        ];
        $response = $this->httpRequest('POST', $url, $data, $this->cookieString, $headers);
        if (json_decode($response['data'])) $response['data'] = json_decode($response['data'], true);
        return $response;
    }
}