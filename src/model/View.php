<?php

// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2025 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace think\model;

use ReflectionClass;
use think\Entity;
use think\Model;

/**
 * 视图模型
 */
abstract class View extends Entity
{
    /**
     * 架构函数.
     *
     * @param Model $model 模型连接对象
     */
    public function __construct(?Model $model = null)
    {
        parent::__construct($model);

        // 获取实体模型参数
        $options = $this->getOptions();
        $this->model()->asView(true);

        // 初始化模型
        $this->initData();
    }

    /**
     * 初始化实体数据属性.
     *
     * @return void
     */
    private function initData()
    {
        if (!$this->model()->isEmpty()) {
            $options    = $this->getOptions();
            $properties = $this->getEntityProperties($options);
            foreach ($properties as $key => $field) {
                if (is_int($key)) {
                    $this->$field = $this->model()->$field;
                } elseif (strpos($field, '->')) {
                    $items  = explode('->', $field);
                    $value  = $this->model();
                    foreach ($items as $item) {
                        $value = $value->$item;
                    }
                    $this->$key = $value;
                } else {
                    $this->$key = $this->model()->$field;
                }
            }
        }
    }

    /**
     * 获取实体属性列表.
     *
     * @param array $options 模型参数
     * @return array
     */
    private function getEntityProperties(array $options = []): array
    {
        $reflection = new ReflectionClass($this);
        $mapping    = $options['property_mapping'] ?? [];
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $field = $property->getName();
            if (isset($mapping[$field])) {
                $properties[$field] = $mapping[$field];
            } else {
                $properties[] = $field;
            }
        }

        return $properties;
    }

    /**
     * 转换为数组.
     *
     * @return array
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * 模型数据转Json.
     *
     * @param int $options json参数
     * @return string
     */
    public function tojson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->toArray(), $options);
    }

    // JsonSerializable
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * 获取属性 支持获取器
     *
     * @param string $name 名称
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        return $this->$name ?? null;
    }

    /**
     * 设置数据 支持类型自动转换
     *
     * @param string $name  名称
     * @param mixed  $value 值
     *
     * @return void
     */
    public function __set(string $name, $value): void
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
    }

    /**
     * 检测数据对象的值
     *
     * @param string $name 名称
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->$name);
    }

    /**
     * 销毁数据对象的值
     *
     * @param string $name 名称
     *
     * @return void
     */
    public function __unset(string $name): void
    {
        __unset($this->$name);
    }

    public function __debugInfo()
    {
        return [];            
    }
}
