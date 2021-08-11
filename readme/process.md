### 自定义进程
由于easyswoole（至少是早期版本的）的 `AbstractProcess` 有bug：对SIGTERM捕获后没有终止当前进程，导致进程无法终止，从而导致整个服务无法被SIGTERM终止，因而不建议直接继承该类创建自定义进程，而是继承修复类 `WecarAbstractProcess`。