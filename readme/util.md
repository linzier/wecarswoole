### 框架提供的 Util 工具

除了 easyswoole 提供的一些工具以外，框架还提供了以下工具供使用（在 \WecarSwoole\Util 下）：

- `AnnotationAnalyser`：注解分析器。
  - `getPropertyAnnotations(string $className, array $annotationFilters = []):array`：获取类属性注解信息
- `File`：文件/目录操作工具，继承 `\EasySwoole\Utility\File`。
  - `join(...$paths):string`：拼接文件名
  - … easyswoole 提供的功能
- `Url`：Url 辅助类。
  - `realUrl(string $path, array $queryParams = [], array $flagParams = [])`：根据配置文件生成绝对 url，支持绝对、相对、伪协议模式如WX://path/to/name会根据配置中心配置转成诸如 https://wx.weicheche.cn/path/to/name。
  - `assemble(string $uri, string $base = '', array $queryParams = [], array $flagParams = []): string`：组装 url
  - `parse(string $url): array`：解析出 schema,host,path,query_string
- `Mock`：模拟数据生成器。
- `CContext`：协程上下文对象。在写协程并发程序时，为防止单例（或静态类）的变量在协程间相互影响，可以使用协程上下文对象来保存变量。用法：
  ```
  <?php
    use WecarSwoole\CContext;

    class ClassName
    {
      private $context;

      private function __construct()
      {
        $this->context = new CContext();
      }
      ...
      public function fuc1()
      {
        ...
        $this->context["foo"] = "bar";
        ...
        $var = $this->context["foo"];
        ...
      }
    }
  ```
- `Concurrent`：并发执行业务逻辑，并等待所有逻辑执行完成后返回所有的执行结果。注意必须在协程上下文中使用。使用实例（注意必须在协程中使用）：
  ```
    // 便捷使用
    $a = $b = $c = 5;
    echo "start:" . time()."\n";
    $r = Concurrent::new()->simpleExec(
        function() use ($a, $b, $c) {
            Co::sleep(1);
            return "$a - $b - $c";
        },
        function () {
            Co::sleep(2);
            return "------";
        },
        function () {
            Co::sleep(1);
            throw new \Exception("我错了", 300);
        }
    );
    echo "end:" . time() . "\n";

    foreach ($r as $rt) {
        if ($rt instanceof \Throwable) {
            echo $rt->getMessage();
        } else {
            echo $rt;
        }
        echo "\n";
    }

    // 更复杂的使用（带传参）
    $r = Concurrent::new();
    $r->addParams(1, 2, 3)
    ->addTask(
        function($a, $b, $c) {
            Co::sleep(1);
            return "$a - $b - $c";
        }
    );
    $r->addParams(4)
    ->addTask(
        function($d) {
          Co::sleep(1);
          return $d;  
        }
    );
    $r->exec();
    
    // 默认情况下不会对外抛出异常，而是将异常对象放在数组中返回。可以通过调用 throwError() 强制直接抛出异常，此时只要有一个任务抛异常则直接对外抛出
    $rsts = Concurrent::new()->throwError()->addTask(...)->addParams(...)->exec();
  ```


[返回](../README.md)

