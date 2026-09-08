<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2023 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace think\model\concern;

use ReflectionClass;
use ReflectionMethod;
use think\db\exception\ModelEventException;
use think\helper\Str;

/**
 * 模型事件处理.
 */
trait ModelEvent
{
    /**
     * Event对象
     *
     * @var object
     */
    protected static $event;

    /**
     * 是否需要事件响应.
     *
     * @var bool
     */
    protected $withEvent = true;

    /**
     * 事件观察者.
     *
     * @var string
     */
    protected $eventObserver;

    /**
     * 设置Event对象
     *
     * @param object $event Event对象
     *
     * @return void
     */
    public static function setEvent($event)
    {
        self::$event = $event;
    }

    /**
     * 当前操作的事件响应.
     *
     * @param bool $event 是否需要事件响应
     *
     * @return $this
     */
    public function withEvent(bool $event)
    {
        $this->withEvent = $event;

        return $this;
    }

    /**
     * after_read 事件，子类可重写.
     *
     * @param Model $model 模型实例
     * @return void
     */
    public static function onAfterRead($model)
    {
    }

    /**
     * before_insert 事件，子类可重写. 返回 false 可阻止写入.
     *
     * @param Model $model 模型实例
     * @return mixed
     */
    public static function onBeforeInsert($model)
    {
    }

    /**
     * after_insert 事件，子类可重写.
     *
     * @param Model $model 模型实例
     * @return void
     */
    public static function onAfterInsert($model)
    {
    }

    /**
     * before_update 事件，子类可重写. 返回 false 可阻止更新.
     *
     * @param Model $model 模型实例
     * @return mixed
     */
    public static function onBeforeUpdate($model)
    {
    }

    /**
     * after_update 事件，子类可重写.
     *
     * @param Model $model 模型实例
     * @return void
     */
    public static function onAfterUpdate($model)
    {
    }

    /**
     * before_write 事件，子类可重写. 返回 false 可阻止写入.
     *
     * @param Model $model 模型实例
     * @return mixed
     */
    public static function onBeforeWrite($model)
    {
    }

    /**
     * after_write 事件，子类可重写.
     *
     * @param Model $model 模型实例
     * @return void
     */
    public static function onAfterWrite($model)
    {
    }

    /**
     * before_delete 事件，子类可重写. 返回 false 可阻止删除.
     *
     * @param Model $model 模型实例
     * @return mixed
     */
    public static function onBeforeDelete($model)
    {
    }

    /**
     * after_delete 事件，子类可重写.
     *
     * @param Model $model 模型实例
     * @return void
     */
    public static function onAfterDelete($model)
    {
    }

    /**
     * before_restore 事件，子类可重写.
     *
     * @param Model $model 模型实例
     * @return void
     */
    public static function onBeforeRestore($model)
    {
    }

    /**
     * after_restore 事件，子类可重写.
     *
     * @param Model $model 模型实例
     * @return void
     */
    public static function onAfterRestore($model)
    {
    }

    /**
     * 触发事件.
     *
     * @param string $event 事件名
     *
     * @return bool
     */
    protected function trigger(string $event): bool
    {
        if (!$this->withEvent) {
            return true;
        }

        $call = 'on' . Str::studly($event);

        try {
            if ($this->eventObserver) {
                $reflect  = new ReflectionClass($this->eventObserver);
                $observer = $reflect->newinstance();
            } else {
                $observer = static::class;
            }

            if (method_exists($observer, $call) && !$this->isTraitDefaultMethod($observer, $call)) {
                $result = $this->invoke([$observer, $call], [$this]);
            } elseif (is_object(self::$event) && method_exists(self::$event, 'trigger')) {
                $result = self::$event->trigger(static::class . '.' . $event, $this);
                $result = empty($result) ? true : end($result);
            } else {
                $result = true;
            }

            return !(false === $result);
        } catch (ModelEventException $e) {
            return false;
        }
    }

    /**
     * 判断给定方法是否为 trait 提供的默认空实现（子类未重写）.
     *
     * 通过比较方法定义文件与 trait 文件路径来判断子类是否真正重写了事件方法，
     * 以确保全局 Event 系统在子类未重写时仍可正常触发.
     *
     * @param object|string $class  类名或实例
     * @param string        $method 方法名
     *
     * @return bool
     */
    private function isTraitDefaultMethod(object|string $class, string $method): bool
    {
        $reflection = new ReflectionMethod($class, $method);
        $traitFile  = (new ReflectionClass(__TRAIT__))->getFileName();

        return $reflection->getFileName() === $traitFile;
    }
}
