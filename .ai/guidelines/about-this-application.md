# 注意事项

## 一、基础规则

- 必须使用中文回复
- 默认使用 PHP 8.5 新特性
- 遵循 SOLID
- 代码必须：可测试、可维护、可扩展
- 禁止过度设计

---

## 二、架构约束

### Controller（必须薄）

只允许：

- 接收参数
- 调用 Action
- 返回响应

禁止：

- 写业务逻辑
- 直接操作 Model
- 组合多个 Action

示例：

```php
public function store(
    StorePostRequest $request,
    SavePostAction $action
) {
    return response()->json(
        $action->execute($request->validated())
    );
}
```

---

### Action（唯一业务层）

- 所有业务逻辑必须在 `app/Actions`
- 每个 Action 单一职责
- 必须 final
- 必须构造函数注入
- 统一暴露 `execute()` 方法
- 禁止 static 业务方法

示例：

```php
final class SaveTemporaryMediaAction
{
    public function __construct(
        private readonly MediaRepository $repository,
    ) {}

    public function execute(UploadedFile $file): Media
    {
        return $this->repository->store($file);
    }
}
```

---

## 强制 TDD

必须遵循：

### Red
- 先写失败测试

### Green
- 写最少代码通过测试

### Refactor
- 消除重复
- 优化结构
- 保证测试通过

禁止先写实现。

---

## 代码质量规则

必须优先使用：

- readonly
- Enum（禁止魔法字符串）
- Match
- 强类型返回
- Constructor Property Promotion

限制：

- 方法 ≤ 20 行
- 嵌套 ≤ 3 层
- 单一职责
- 禁止隐式副作用

---

## 测试规则

- Action → Unit Test
- Controller → Feature Test
- 每个测试只验证一个行为
- 命名表达业务语义

示例：

```php
it('stores temporary media successfully');
```

---

## 核心优先级

1. 可测试性
2. 可读性
3. 明确性
4. 简洁性
5. 性能优化最后考虑
