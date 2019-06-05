用户中心
----
#### 目录结构：
参照 laravel 目录结构设计
- app 项目程序目录。该目录下的子目录和文件名都开头大写。
    - Bean  DTO（数据传输对象）放置在此。
            数据传输对象不属于领域层对象，属于用例维度对象（即属于应用层的东西），一般可以定义Bean对象来实现用例查询优化
    - Console   控制台控制器（一般做命令行客户端交互程序）
    - Http  Http 请求处理组件（对外提供 Restful API 服务）
        - Controllers Http 控制器。建议按模块组织存放
            - V1 版本控制
                - $moduleDirs 具体的模块目录，如果项目比较小，可以先不划分模块
        - Routes Http 路由配置，建议按模块组织存放。在这里面定义路由规则。
                通过添加路由中间件可实现诸如鉴权、数据处理等功能。
                （为何不在基类控制器中做：我们尽量保证控制器的简单；另外，一个控制器可以对应多个路由，这样对不同对路由添加不同对中间件可以实现在同一个控制器上实现不同对鉴权方式等灵活实现）
        - Middleware 中间件
    - Cron 定时任务，需要继承 `EasySwoole\EasySwoole\Crontab\AbstractCronTask`
      [参见](http://www.easyswoole.com/Manual/3.x/Cn/_book/BaseUsage/crontab.html)
        > 注意：多个定时任务的 taskName 不能重复，否则会相互覆盖
    - Tasks 异步任务，需要继承`\EasySwoole\EasySwoole\Swoole\Task\AbstractAsyncTask`
      [参见](http://www.easyswoole.com/Manual/3.x/Cn/_book/BaseUsage/async_task.html)
    - Domain 业务领域逻辑，编写具体的业务逻辑，这里面的类包含但不限于：实体、值对象、领域服务、仓储、领域事件。里面的文件按模块自由组织。
        - Events 领域事件
    - Util 项目私有但辅助类/函数。注意：公共辅助类请使用 Composer 库。
    - Exceptions 异常定义类
    - Process 自定义进程
    - Foundation 基础设施，如事件总线，数据库驱动，缓存驱动等。一般 Foundation 会抽离成单独的 Composer 包，各项目的 Foundation 用来做必要的包装。
    - AppService 应用服务
    - Subscribers 事件订阅者（所属层次同 Controller，属于处理程序，应用层）
- tests 单元测试目录，和 app目录结构相同。一般要重点针对 Domain 编写单元测试。
- config 配置文件目录
- storage   文件生成目录
    - app   项目生成或使用的文件
    - cache 本地缓存目录
    - logs  日志目录
- vendor composer 包安装目录，需加入.gitignore 中

#### 有别于传统 MVC 的分层设计（DDD 推荐的划分方式）：
- 表示层。展示UI/数据、接收用户的输入，直接和用户打交道（用户可能是人也可能是其他系统）。如前端。
- 应用层。从用户的维度定义系统需要完成的**任务**。应用层只定义任务，不负责具体实现。如这里的 Console、Http、Cron（实际上它们也承担了部分表示层职责）。
- 领域层。业务逻辑的具体实现。应用层调用领域层实现具体的任务。这里的 Domain目录下的代码。
- 基础设施层。提供诸如 DB、Cache、SESSION 等业务无关的基础支持。

[参考文章](https://kb.cnblogs.com/page/112298/)

#### 目录划分说明：
- 大体分为几类：
    - 表示层 + 应用层：Console、Cron、Http等，对外提供不同的接口（命令行、定时任务、Http API），属于"处理器"，这里不应写具体的业务逻辑。
    - 领域层：Domain。这里放具体的业务逻辑代码，属于系统核心。注意这里侵入其他层代码（如应用层的 SESSION、Cookie 等。仓储除外，因为仓储本身是和存储层密切相关的）。
    - 基础设施层：Utils。提供非业务领域的基础设施支持（此处仅列类Utils，因为像 Email、DB等都是通过 Composer 包安装的）。
- 缺失的 Model：
    由于 Model 的具体含义有分歧，容易被乱用，实际中大部分时候被用作 ORM 和 DTO，并非真正意义上的"对象"（起的是数据结构作用），因而我们并没有引入 Model 的概念，而是引入 DDD 设计中的**仓储**概念。
- 缺失的 Logic:
    Logic 一词同样含义模糊（一切皆逻辑），而且实际使用中过于扁平化，导致代码过于臃肿。取而代之，我们引入更加具有含义、更具纵深、更加灵活的 DDD 中的**Domain**概念（业务领域），让开发者根据实际情况自己组织代码层次。
- Domain: 业务逻辑具体实现。Domain 里面主要包含
    - Domain Service。领域服务，聚合领域对象的功能对应用层或其他领域服务提供粗粒度功能。
    - Domain Object。狭义的领域对象，包括 Entity（实体）和 Value Object(值对象)，提供细粒度的单一职责实现。多个 Domain Object 可以聚合在一起提供一个比较完整的功能。
    - Aggregate。聚合，简而言之就是一个或几个 Domain Object 组合在一起对外提供相对完整的功能。
    - Repository。仓储，领域对象与数据存储之间的纽带。仓储接收 Aggregate 并存入存储层（如数据库），并根据查询条件从存储层查询数据并还原为 Aggregate。
- 为何 Http/Controllers 下面有 V1 这样的版本划分？
    Http/Controller 是系统最主要的对外 API，API 一旦定义则很难做不兼容修改（比如改个字段名，删掉某字段，改变字段含义等），因而可以采用版本控制，对外提供不同版本的 API(这也是为何 Restful API 的 uri 中包含版本的原因)。
    不同与 API，Domain 不需要版本概念，因为 Web 产品一般只会演化而不会分版本。当然，会有定制化需求，可以采用其他方案处理。
    

#### 其它：
- Cache: EasySwoole 没有提供缓存组件，项目使用 symfony/cache 的 PSR-16 规范的 SimpleCache。
- Redis: 项目没有使用 EasySwoole 的 RedisPool，而是使用 phpredis 扩展自带的连接池。php.ini配置：
    extension=redis.so
    redis.pconnect.pooling_enabled=1 
- Http Client: EasySwoole 自带的 http client 仅支持 get 和 post 请求，无法满足需求，故使用第三方库 swlib/saber （swoole 官方推荐）
- 异常处理机制：遵循业界最主流的做法：抛出异常，而不是返回错误码。（遵循业务逻辑和错误处理分离原则）
- 依赖注入：使用 [PHP-DI](http://php-di.org/doc/getting-started.html)
  附：[依赖注入最佳实践](http://php-di.org/doc/best-practices.html)
    - 在控制器中使用注解注入依赖；
    - 其它类（如 Service）使用构造函数注入依赖（保证可重用性和可测试性）；
    - 不要在程序中使用 $container->get(...)，造成程序对容器对依赖；
    - 推荐使用接口类型提示，在 config 中配置接口对应的实现；
    （生产环境需要开启编译，每次发版的时候要重新编译，建议采用预编译），开发环境关闭编译
    不会编构造函数注入的和注解注入的，如果要优化这些，需要开启 cache(需要apcu扩展)
- 开发实践：
    - Controller 中保持简洁，不要在控制器中写业务代码；
    - 不要在业务逻辑中直接获取/使用 Session、Request、Response、Cookie、Header、Container、DI、Config 等全局变量和框架相关的东西，保证业务逻辑代码是框架无关的而且是可测试的；
- Controller
    - 禁止使用静态属性
    - 禁止使用私有属性（因为 EasySwoole 使用对象池技术，每次请求结束并不会重置私有属性，导致私有属性的修改影响后续请求）
    - 构造器中一定要在最后（而不是前面）再调用 parent::__construct()，否则后续请求无法访问这里面设置的属性
- 关于让 EasySwoole 支持 PHP-DI：需要修改 easyswoole/http 的 Dispatcher。
  为了保证稳定性，需要fork easy-swoole/easyswoole 和 easy-swoole/http，修改里面的代码和依赖关系，使用fork版本
- Repository 和 Service 不能有状态信息
- 一般不要用 easyswoole stop force，从其实现来看，会造成僵尸进程
- 日志：使用 monolog/monolog。不适用 easyswoole 自带的 Logger（不符合 PSR 规范，没有日志级别控制等）
- email: 使用 SwiftMail 扩展
- 事件：使用 symfony/event-dispatcher 扩展。[参考](https://symfony.com/doc/current/components/event_dispatcher.html)

#### 系统设计注意事项：
- 扩展性
- 容易和第三方系统对接（需要设计对接标准方案）
- 可测试
- 遵循 PSR 规范

#### 工厂
- MySQL、Redis、Email、Cache、Logger 等基础设施都有相应工厂来创建，工厂依赖于 EasySwoole（主要依赖于配置），并且将具体的基础设施扩展与 EasySwoole 框架隔离（即扩展本身不依赖于框架）。
- 工厂返回的基础设施尽量符合 PSR 规范（如 Cache、Logger 等）
- 虽然提供了工厂，但实际使用中不建议直接用工厂获取对象（工厂并不提供单例模式），项目中请用 IoC 注入（本项目用的是 PHP-DI，建议通过构造函数注入这些基础设施）