<?php

namespace Airmole\TjustbAuthsys;

use Airmole\TjustbAuthsys\Exception\Exception;

/**
 * 系统主方法
 */
class Authsys
{
    /**
     * 用户账号
     * @var string
     */
    public string $usercode;

    /**
     * 已登录cookie
     * @var array
     */
    public array $cookie;

    /**
     * 目标系统登录URL
     * @var array
     */
    public const TARGET_SYSTEMS = [
        'ehall' => 'http://ehall.bkty.top/login',                       // 网上办事大厅
        'opac_lan' => 'http://10.1.254.98:82/reader/hwthau.php',        // 图书馆OPAC内网
        'opac_wan' => 'http://opac.bkty.top/reader/hwthau.php',         // 图书馆OPAC公网
        'fina_lan' => 'http://10.2.254.80:8809/Login/JinZhi_Login',     // 学生收费系统内网
        'fina_wan' => 'http://221.238.213.131:8809/Login/JinZhi_Login', // 学生收费系统公网
    ];

    /**
     * 获取登录所需参数
     * @return array
     * @throws Exception
     */
    public function loginPara(): array
    {
        $login = new Login();
        return $login->loginPara();
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
        $login = new Login();
        $result = $login->login($usercode, $password, $params);
        if ($result['code'] == Base::CODE_SUCCESS) {
            $this->usercode = $usercode;
            $this->cookie = $login->cookieArray;
        }
        return $result;
    }

    /**
     * 注销登录
     * @return array
     * @throws Exception
     */
    public function logout(): array
    {
        $login = new Login();
        $login->cookieArray = $this->cookie;
        $login->cookieString = $login->getCookieString($login->cookieArray);
        return $login->logout();
    }

    /**
     * 获取在线应用列表
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array
     * @throws Exception
     */
    public function appList(int $page = 1, int $pageSize = 100): array
    {
        $app = new App($this->usercode, $this->cookie);
        return $app->appList($page, $pageSize);
    }

    /**
     * 获取应用名称列表
     * @return array
     * @throws Exception
     */
    public function appNameList(): array
    {
        $app = new App($this->usercode, $this->cookie);
        return $app->appNameList();
    }

    /**
     * 统一认证访问其他系统
     * @param string $targetUrl 目标访问系统
     * @return array
     * @throws Exception
     */
    public function visitOtherSystem(string $targetUrl = ''): array
    {
        $app = new App($this->usercode, $this->cookie);
        return $app->visitOtherSystem($targetUrl);
    }

    /**
     * 获取账号在线列表
     * @return array
     * @throws Exception
     */
    public function onlineList(): array
    {
        $log = new Log($this->usercode, $this->cookie);
        return $log->onlineList();
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
     * @throws Exception
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
        $log = new Log($this->usercode, $this->cookie);
        return $log->loginLogs($startTime, $endTime, $page, $pageSize, $result, $loginLocation, $typeCode, $appName);
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
     * @throws Exception
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
        $log = new Log($this->usercode, $this->cookie);
        return $log->accessAppLogs($startTime, $endTime, $page, $pageSize, $result, $typeCode, $appName, $appId);
    }

    /**
     * getUserConf接口
     * @return array
     * @throws Exception
     */
    public function getUserConf(): array
    {
        $setting = new Setting($this->usercode, $this->cookie);
        return $setting->getUserConf();
    }

    /**
     * 获取账号设置
     * @return array
     * @throws Exception
     */
    public function accountSetting(): array
    {
        $setting = new Setting($this->usercode, $this->cookie);
        return $setting->accountSetting();
    }

    /**
     * 解析Cookie字符串
     * @param string $cookieString Cookie字符串
     * @return array
     */
    public function parseCookieString(string $cookieString = ''): array
    {
        $base = new Base();
        $cookieArray = $base->parseCookieString($cookieString);
        $this->cookie = $cookieArray;
        return $cookieArray;
    }

    /**
     * Cookie数组转字符串
     * @param array $cookie Cookie数组
     * @return string
     */
    public function parseCookieArray(array $cookie = []): string
    {
        $base = new Base();
        $this->cookie = $cookie;
        return $base->parseCookieArray($cookie);
    }

}