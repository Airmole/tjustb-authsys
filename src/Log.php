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
        $this->validateAuthContext($usercode, $cookie);
    }

    /**
     * 获取账号在线列表
     * @return array
     */
    public function onlineList(): array
    {
        $url = '/personalInfo/UserOnline/user/queryUserOnline';
        $result = $this->httpRequest('GET', $url, '', $this->cookieString, $this->jsonHeaders());
        return $this->decodeJsonData($result);
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
    ): array {
        return $this->queryLogs(
            [
                'operType' => 0,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'pageIndex' => $page,
                'pageSize' => $pageSize,
                'result' => $result,
                'loginLocation' => $loginLocation,
                'typeCode' => $typeCode,
                'appName' => $appName,
            ]
        );
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
    ): array {
        return $this->queryLogs(
            [
                'operType' => 3,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'pageIndex' => $page,
                'pageSize' => $pageSize,
                'result' => $result,
                'typeCode' => $typeCode,
                'appName' => $appName,
                'appId' => $appId,
            ]
        );
    }

    /**
     * 通用日志查询
     * @param array $data 查询参数
     * @return array
     */
    private function queryLogs(array $data): array
    {
        $url = '/personalInfo/UserLogs/user/queryUserLogs';
        $body = json_encode($data);
        $response = $this->httpRequest('POST', $url, $body, $this->cookieString, $this->jsonHeaders());
        return $this->decodeJsonData($response);
    }
}
