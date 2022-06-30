### 发短信

```
use WecarSwoole\SMS;

SMS::getInstance()->send($mobile, $message);
```

**注意：** 短信内部是通过 `API::invoke('weicheche:sms.send', $data);` 实现的，所以项目的 api.php 配置中必须要有 weicheche 这个组（框架默认会生成该组，且已经配置好了短信网关的信息，不要删了）。