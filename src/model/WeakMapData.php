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

use think\Model;

/**
 * Class WeakMapData.
 * WeakMap数据存储类
 */
class WeakMapData
{
    private static $data = [];

	public static function set(string $class, array $value)
	{
		self::$data[$class] = $value;
	}

	public static function has(string $class): bool
	{
		return isset(self::$data[$class]);
	}

	public static function get(string $class): array
	{
		if (self::has($class)) {
			$data = self::$data[$class];
			unset(self::$data[$class]);
			return $data;
		}
		return [];
	}
}
