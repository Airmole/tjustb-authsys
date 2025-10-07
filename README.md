# tjustb-authsys

Tianjin college, USTB 统一认证系统（SSO）HTTP 客户端 PHP SDK。

- Composer 包名：airmole/tjustb-authsys
- PHP 要求：>= 8.0
- 依赖扩展：ext-curl, ext-json, ext-openssl
- 许可证：GPL-3.0-or-later
- 命名空间：Airmole\TjustbAuthsys

## 功能概览

- 登录参数获取与登录、注销
- 在线应用列表、应用名称列表
- 统一认证访问其他系统（生成跳转 URL 与 ticket）
- 在线设备列表
- 登录日志与访问应用日志查询
- 账号设置查询与用户配置获取
- Cookie 字符串与数组互转、从响应头解析 Cookie/Location
- 自动加载配置：统一认证系统地址、请求超时

## 安装

使用 Composer 安装：

```bash
composer require airmole/tjustb-authsys
```

## 配置

SDK 支持通过环境变量或 .env 文件配置。

支持的配置键：
- `AUTHSYS_URL`：统一认证系统根地址，默认 `http://authserver.bkty.top`
- `AUTHSYS_TIMEOUT`：请求超时时间（秒），默认 `5`
- `AUTHSYS_ENV`：自定义 .env 文件绝对路径；若不设置，则默认读取项目根目录上级的 `.env`（即 `src/..` 的同级目录）

示例 `.env`：
```
AUTHSYS_URL=https://authserver.example.edu
AUTHSYS_TIMEOUT=8
```

也可在运行时设置：
```php
use Airmole\TjustbAuthsys\Base;

$base = new Base();
$base->setAuthsysUrl('https://authserver.example.edu');
```

## 快速开始

```php
use Airmole\TjustbAuthsys\Authsys;
use Airmole\TjustbAuthsys\Exception\Exception;

$auth = new Authsys();

try {
    // 1) 获取登录参数（包含 salt、execution 及初始 cookie）
    $params = $auth->loginPara();

    // 2) 登录
    $usercode = '2020xxxxxx';
    $password = 'your_password';
    $result = $auth->login($usercode, $password, $params);
    if ($result['code'] !== 200) {
        // 可能是 ['code'=>403,'data'=>'账号或密码错误'] 等
        throw new Exception("登录失败: " . $result['data']);
    }

    // 3) 获取在线应用列表
    $apps = $auth->appList(page: 1, pageSize: 50); // ['code'=>200,'data'=>数组或原始字符串]

    // 4) 获取应用名称列表
    $appNames = $auth->appNameList();

    // 5) 获取登录日志
    $logs = $auth->loginLogs(
        startTime: '2024-09-01 00:00:00',
        endTime: '2024-12-31 23:59:59',
        page: 1,
        pageSize: 20,
        result: '',
        loginLocation: '',
        typeCode: '',
        appName: ''
    );

    // 6) 获取访问应用日志
    $accessLogs = $auth->accessAppLogs(
        startTime: null,
        endTime: null,
        page: 1,
        pageSize: 10,
        result: '',
        typeCode: '',
        appName: '',
        appId: ''
    );

    // 7) 访问其他系统（统一认证跳转）
    $visit = $auth->visitOtherSystem('https://target.example.edu/app');
    // $visit = ['url' => 最终跳转URL, 'ticket' => '...']

    // 8) 用户配置 & 账号设置（需已登录 cookie）
    $userConf = $auth->getUserConf();
    $accountSetting = $auth->accountSetting();

    // 9) 注销登录
    $logoutRes = $auth->logout();

} catch (Exception $e) {
    // 捕获 SDK 抛出的异常（网络错误、流程异常等）
    echo "异常: " . $e->getMessage();
}
```

## API 说明

除非特别说明，方法成功时通常返回：
- `['code' => 200, 'data' => mixed]`：data 可能是字符串或已解析为数组（SDK 会尝试 json_decode）
- 部分内部跳转过程会返回 302，用于流程控制；对外暴露的方法若遇异常会抛出 `Exception`

### 类 Authsys
- `public function loginPara(): array`
  - 获取登录所需参数（salt、execution、初始 cookie 等）
- `public function login(string $usercode, string $password, array $params = []): bool|array`
  - 登录成功：`['code'=>200,'data'=>'success']`，并在对象上设置 `usercode` 与 `cookie`
  - 登录失败：返回带错误提示的数组，如 `['code'=>403,'data'=>'用户名或密码错误']`；特殊错误可能抛出 `Exception`
- `public function logout(): array`
  - 注销登录，成功 `['code'=>200,'data'=>'success']`
- `public function appList(int $page = 1, int $pageSize = 100): array`
  - 获取在线应用列表
- `public function appNameList(): array`
  - 获取可用于筛选日志的应用名称列表
- `public function visitOtherSystem(string $targetUrl = ''): array`
  - 返回 `['url'=>跳转URL,'ticket'=>CAS ticket]`
- `public function onlineList(): array`
  - 当前账号在线设备列表
- `public function loginLogs(...): array`
  - 登录日志，支持时间窗口、结果、地点、类型、应用名等筛选
- `public function accessAppLogs(...): array`
  - 访问应用日志，支持 `appId` 等筛选
- `public function getUserConf(): array`
  - 获取用户配置
- `public function accountSetting(): array`
  - 获取账号安全设置
- `public function parseCookieString(string $cookieString = ''): array`
  - `"Cookie: a=b; c=d"` 转数组，并写入对象 cookie
- `public function parseCookieArray(array $cookie = []): string`
  - Cookie 数组转字符串，并写入对象 cookie

### 类 Base（部分常用能力）
- 常量：`CODE_SUCCESS=200`, `CODE_REDIRECT=302`
- 配置
  - `setConfigPath(string $path=''): void`
  - `setAuthsysUrl(string $url='http://authserver.bkty.top'): void`
  - `getConfig(string $key, $default=null, string $path=''): mixed`
- HTTP
  - `httpRequest(string $method, string $url, mixed $body, mixed $cookie, array $headers=[], bool $showHeaders=false, bool $followLocation=false, int $timeout=5): array`
- Cookie/头部工具
  - `insertCookie(string $key, string $value): void`
  - `getCookieString(array $cookie=[]): string`
  - `parseCookieString(string $cookieString=''): array`
  - `parseCookieArray(array $cookie=[]): string`
  - `getCookieFromHeader(string $key, string $headerString=''): string`
  - `getLocationFromRedirectHeader(string $header=''): string`

### 类 Login（内部被 Authsys 使用）
- `loginPara(): array`
- `login(string $usercode, string $password, array $params=[]): bool|array`
- `logout(): array`
- `validateLoginResult(array $response): bool|array`
- `encryptPassword(string $data, string $aesKey): string`
- `decryptPassword(string $data, string $aesKey): string`
- `randomString(int $length): string`

### 类 App（需已登录 Cookie 与 usercode）
- `appList(int $page=1, int $pageSize=100): array`
- `appNameList(): array`
- `visitOtherSystem(string $targetUrl=''): array`

### 类 Log（需已登录 Cookie 与 usercode）
- `onlineList(): array`
- `loginLogs(...): array`
- `accessAppLogs(...): array`

### 类 Setting（需已登录 Cookie 与 usercode）
- `getUserConf(): array`
- `accountSetting(): array`

### 异常
- 命名空间：`Airmole\TjustbAuthsys\Exception\Exception`
- SDK 在流程异常、系统异常时会抛出该异常，请注意捕获

## 返回与错误处理

- 正常成功：`['code'=>200,'data'=>...]`
- 流程性跳转：内部使用 302 控制
- 登录失败常见返回（`Login::validateLoginResult`）：
  - 账号或密码错误 / 用户名或密码错误 / 登录凭证不可用 / 失败次数过多 / 学校系统不稳定 等
- 网络或解析异常：抛出 `Exception`
- JSON 响应：SDK 会尝试 `json_decode`；若能解析则将 `data` 替换为数组

## 使用注意事项

- 登录流程依赖初始参数（salt、execution、初始 cookie），请先调用 `loginPara()` 再 `login()`
- 多个后续接口需要 `REFERERCE_TOKEN`、`happyVoyage`、`MOD_AUTH_CAS`、`JSESSIONID` 等 Cookie，SDK 登录成功后会自动维护 `cookieArray` 与 `cookieString`
- `App::appNameList` 要求请求体携带一个随机数字段，否则服务端可能返回 400，SDK 已内置处理
- 建议将 `AUTHSYS_URL` 指向生产环境认证地址；超时时间可通过 `AUTHSYS_TIMEOUT` 调整
- 本项目用于对接学校统一认证系统，请遵守学校与相关系统的使用规范，不要进行非授权的自动化访问

## 示例：Cookie 直接复用

若已从浏览器获取 Cookie，可直接注入后使用：

```php
use Airmole\TjustbAuthsys\Authsys;

$auth = new Authsys();
$cookieString = 'MOD_AUTH_CAS=...; happyVoyage=...; REFERERCE_TOKEN=...; JSESSIONID=...';
$auth->parseCookieString($cookieString);

// 现在可直接调用需要登录态的接口
$apps = $auth->appList();
```

或从数组生成：

```php
$cookieArray = [
  'MOD_AUTH_CAS' => '...',
  'happyVoyage' => '...',
  'REFERERCE_TOKEN' => '...',
  'JSESSIONID' => '...',
];
$cookieString = $auth->parseCookieArray($cookieArray);
```

## 开发与测试建议

- 建议在 `.env` 中配置测试环境 URL 与合适的超时
- 捕获并记录 `Exception`，以便定位网络/重定向异常
- 对频繁接口添加合理节流，避免触发风控
- 可使用代理/抓包工具（如 mitmproxy/Charles）辅助排查

## 许可证

GPL-3.0-or-later

鸣谢
- 作者: Airmole (admin@airmole.cn)