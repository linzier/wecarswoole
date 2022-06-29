<?php

use function WecarSwoole\Config\apollo;
use WecarSwoole\Util\File;
use WecarSwoole\LogHandler\WecarFileHandler;

$baseConfig = [
    'app_name' => '用户系统',
    // 应用标识
    'app_flag' => 'YH',
    'app_id' => 10017,
    'request_id_key' => 'wcc-request-id',
    'server' => [
        'modules' => apollo('fw.modules'),
        'app_ids' => apollo('fw.appids'),
    ],
    // 邮件。可以配多个
    'mailer' => [
        'default' => [
            'host' => apollo('fw.mail', 'mail.host'),
            'username' => apollo('fw.mail', 'mail.username'),
            'password' => apollo('fw.mail', 'mail.password'),
            'port' => apollo('fw.mail', 'mail.port') ?: 25,
            'encryption' => apollo('fw.mail', 'mail.encryption') ?: 'ssl',
        ]
    ],
    // 并发锁配置
    'concurrent_locker' => [
        'onoff' => apollo('application', 'concurrent_locker.onoff') ?: 'off',
        'redis' => apollo('application', 'concurrent_locker.redis') ?: 'main',
    ],
    // 请求日志配置。默认是关闭的，如果项目需要开启，则自行修改为 on
    'request_log' => [
        'onoff' => apollo('application', 'request_log.onoff') ?: 'off',
        // 记录哪些请求类型的日志
        'methods' => explode(',', apollo('application', 'request_log.methods'))
    ],
    // api调用日志（本系统调别的系统的日志）
    'api_invoke_log' => apollo('application', 'api_invoke_log') ?: 'on',
    /**
     * 数据库配置建议以数据库名作为 key
     * 如果没有读写分离，则可不分 read, write，直接在里面写配置信息
     */
    'mysql' => [
        'weicheche' => [
            // 读库使用二维数组配置，以支持多个读库
            'read' => [
                [
                    'host' => apollo('fw.mysql.weicheche.ro', 'weicheche_read.host'),
                    'port' => apollo('fw.mysql.weicheche.ro', 'weicheche.port'),
                    'user' => apollo('fw.mysql.weicheche.ro', 'weicheche_read.username'),
                    'password' => apollo('fw.mysql.weicheche.ro', 'weicheche_read.password'),
                    'database' => apollo('fw.mysql.weicheche.ro', 'weicheche_read.dbname'),
                    'charset' => apollo('fw.mysql.weicheche.ro', 'weicheche_read.charset'),
                ]
            ],
            // 仅支持一个写库
            'write' => [
                'host' => apollo('fw.mysql.weicheche.rw', 'weicheche.host'),
                'port' => apollo('fw.mysql.weicheche.rw', 'weicheche.port'),
                'user' => apollo('fw.mysql.weicheche.rw', 'weicheche.username'),
                'password' => apollo('fw.mysql.weicheche.rw', 'weicheche.password'),
                'database' => apollo('fw.mysql.weicheche.rw', 'weicheche.dbname'),
                'charset' => apollo('fw.mysql.weicheche.rw', 'weicheche.charset'),
            ],
            // 连接池配置
            'pool' => [
                'size' => apollo('application', 'mysql.weicheche.pool_size') ?: 15
            ]
        ],
    ],
    'redis' => [
        'main' => [
            'host' => apollo('fw.redis.01', 'redis.host'),
            'port' => apollo('fw.redis.01', 'redis.port'),
            'auth' => apollo('fw.redis.01', 'redis.auth'),
            'database' => apollo('fw.redis.01', 'redis.database') ?? 0,
            // 连接池配置
            '__pool' => [
                'max_object_num' => apollo('application', 'redis.pool.main.max_num') ?? 15,
                'min_object_num' => apollo('application', 'redis.pool.main.min_num') ?? 1,
                'max_idle_time' => apollo('application', 'redis.pool.main.idle_time') ?? 300,
            ],
        ],
        'cache' => [
            'host' => apollo('fw.redis.01', 'redis.host'),
            'port' => apollo('fw.redis.01', 'redis.port'),
            'auth' => apollo('fw.redis.01', 'redis.auth'),
            'database' => apollo('fw.redis.01', 'redis.database') ?? 0,
            // 连接池配置
            '__pool' => [
                'max_object_num' => apollo('application', 'redis.pool.cache.max_num') ?? 15,
                'min_object_num' => apollo('application', 'redis.pool.cache.min_num') ?? 1,
                'max_idle_time' => apollo('application', 'redis.pool.cache.idle_time') ?? 300,
            ]
        ],
    ],
    // 缓存配置
    'cache' => [
        // 可用：redis、file、array、null(一般测试时用来禁用缓存)
        'driver' => apollo('application', 'cache.driver'),
        'prefix' => 'usercenter',
        // 缓存默认过期时间，单位秒
        'expire' => 3600,
        // 当 driver = redis 时，使用哪个 redis 配置
        'redis' => 'cache',
        // 当 driver = file 时，缓存存放目录
        'dir' => File::join(EASYSWOOLE_ROOT, 'storage/cache'),
    ],
    // 最低记录级别：debug, info, warning, error, critical, off
    'log_level' => apollo('application', 'log_level') ?: 'info',
    'base_url' => apollo('application', 'base_url'),
    // 是否开启 SQL 日志（日志级别是info）
    'sql_log' => apollo('application', 'sql_log') ?: 'off',
    'max_log_file_size' => apollo('application', 'max_log_file_size') ?: WecarFileHandler::DEFAULT_FILE_SIZE,
    // 是否对相关接口进行 token 校验（继承 ApiRoute 的接口）。默认需要验证，此参数主要用来临时取消验证进行测试
    'auth_request' => apollo('application', 'auth_request') ?? 1,
    /**
     * 登录会话相关
     */
    // 是否开启 jwt 认证（继承 JWTRoute 的接口）
    'jwt_auth_on' => apollo('application', 'jwt_auth_on') ?? 1,
    // jwt 签名用的 key，需要保证足够的强度
    // PHP7 以上建议用 bin2hex(random_bytes(32)) 生成
    'jwt_sign_key' => apollo('application', 'jwt_sign_key') ?? '',
    // 是否对 jwt 串加密。需配合 jwt_secret 使用，没提供 jwt_secret 则一定不加密
    'jwt_encrypt_on' => apollo('application', 'jwt_encrypt_on') ?? 1,
    // jwt token 过期时间
    'jwt_expire' => apollo('application', 'jwt_expire') ?? 3600 * 3,
    // jwt 内容加密用的 secret，留空则不加密
    'jwt_secret' => apollo('application', 'jwt_secret') ?? '',
    /**
     * 输入安全
     */
    // xss 攻击防御
    // 如果系统接受用户输入，则强烈建议开启；如果仅由其他系统调用，且调用参数可控，则可不开启
    // 默认关闭。因为很多系统是作为服务供其他系统调用，无需开启（开启后会些许影响性能）
    'xss_filter' => apollo('application', 'xss_filter_on') ?? 0,
    // 去除输入参数的首尾空格
    'trim_whitespace' => apollo('application', 'trim_whitespace') ?? 1,
];

return array_merge(
    $baseConfig,
    ['logger' => include_once __DIR__ . '/logger.php'],
    ['api' => require_once __DIR__ . '/api/api.php'],
    ['subscriber' => require_once __DIR__ . '/subscriber/subscriber.php']
);
