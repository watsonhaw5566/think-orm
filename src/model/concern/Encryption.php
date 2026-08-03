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

use think\Model;
use ReflectionClass;

/**
 * 模型字段加密
 *
 * 用于自动加密/解密模型中的敏感字段（如手机号、身份证号、密码提示等）。
 * 写入数据时自动加密，读取数据时自动解密，对业务代码透明。
 *
 * 使用方法:
 * 1. 在模型中 use Encryption trait
 * 2. 配置需要加密的字段 protected $encryptFields = ['mobile', 'id_card'];
 * 3. （推荐）配置加密密钥 protected $encryptKey = 'your-secret-key';
 *
 * <code>
 * class User extends Model
 * {
 *     use \think\model\concern\Encryption;
 *     protected $encryptFields = ['mobile', 'email'];
 *     protected $encryptKey = 'your-secret-key-here';
 * }
 * </code>
 *
 * @mixin Model
 */
trait Encryption
{
    /**
     * 回退用的数据存储容器。
     *
     * 当使用该 trait 的宿主类没有继承 think\Model（比如单元测试中的轻量宿主类）时，
     * setAttr / getValue / set 方法无法调用 parent，会退化为直接读写本数组，
     * 以避免在无父类场景下出现 class.noParent 问题，同时保持加解密行为正常。
     *
     * 在真实 think\Model 环境下，宿主类自身就携带 $data，该属性不会被使用。
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * 判断当前宿主类是否存在父级同名方法
     */
    private function parentMethodExists(string $method): bool
    {
        $parent = get_parent_class($this);
        if (false === $parent || '' === $parent) {
            return false;
        }

        return method_exists($parent, $method);
    }

    /**
     * 获取需要加密的字段列表
     *
     * @return array
     */
    protected function getEncryptFields(): array
    {
        return property_exists($this, 'encryptFields') && is_array($this->encryptFields)
            ? $this->encryptFields
            : [];
    }

    /**
     * 获取加密密钥
     *
     * @return string
     */
    protected function getEncryptKey(): string
    {
        if (property_exists($this, 'encryptKey') && !empty($this->encryptKey)) {
            return $this->encryptKey;
        }

        return 'think-orm-default-key';
    }

    /**
     * 获取加密算法
     *
     * @return string
     */
    protected function getEncryptCipher(): string
    {
        if (property_exists($this, 'encryptCipher') && !empty($this->encryptCipher)) {
            return $this->encryptCipher;
        }

        return 'AES-128-ECB';
    }

    /**
     * （静态）加密值
     *
     * 用于无需实例化模型时的场景，例如搜索时先加密查询条件。
     * 会优先读取模型类上配置的 $encryptKey / $encryptCipher。
     *
     * <code>
     *     $enc = User::encryptValue('13800138000');
     *     User::where('mobile', $enc)->find();
     * </code>
     *
     * @param mixed $value  待加密的值
     *
     * @return string|null
     */
    public static function encryptValue(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value  = (string) $value;
        $key    = static::resolveEncryptKey();
        $cipher = static::resolveEncryptCipher();

        $encrypted = openssl_encrypt($value, $cipher, $key, OPENSSL_RAW_DATA);

        return base64_encode($encrypted);
    }

    /**
     * （静态）解密值
     *
     * @param mixed $value  待解密的值
     *
     * @return string|null
     */
    public static function decryptValue(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $key    = static::resolveEncryptKey();
        $cipher = static::resolveEncryptCipher();

        $decoded = base64_decode($value, true);

        if (false === $decoded) {
            return null;
        }

        $decrypted = openssl_decrypt($decoded, $cipher, $key, OPENSSL_RAW_DATA);

        return false === $decrypted ? null : $decrypted;
    }

    /**
     * 解析模型类的加密密钥（静态上下文中使用）
     *
     * @return string
     */
    protected static function resolveEncryptKey(): string
    {
        /** @noinspection PhpUndefinedFieldInspection */
        $ref = new ReflectionClass(static::class);

        $defaults = $ref->getDefaultProperties();

        if (isset($defaults['encryptKey']) && !empty($defaults['encryptKey'])) {
            return (string) $defaults['encryptKey'];
        }

        $instance = $ref->newInstanceWithoutConstructor();
        if ($instance instanceof Model && method_exists($instance, 'getEncryptKey')) {
            return $instance->getEncryptKey();
        }

        return 'think-orm-default-key';
    }

    /**
     * 解析模型类的加密算法（静态上下文中使用）
     *
     * @return string
     */
    protected static function resolveEncryptCipher(): string
    {
        $ref      = new ReflectionClass(static::class);
        $defaults = $ref->getDefaultProperties();

        if (isset($defaults['encryptCipher']) && !empty($defaults['encryptCipher'])) {
            return (string) $defaults['encryptCipher'];
        }

        return 'AES-128-ECB';
    }

    /**
     * 判断字段是否需要加密
     *
     * @param string $name 字段名
     *
     * @return bool
     */
    protected function isEncryptField(string $name): bool
    {
        $fields = $this->getEncryptFields();
        $name   = $this->getRealFieldName($name);

        return in_array($name, $fields, true);
    }

    /**
     * 加密字符串
     *
     * @param mixed $value 待加密的值（null/标量/可转字符串对象）
     *
     * @return string|null 加密后的值（null 原样返回）
     */
    public function encrypt(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $value  = (string) $value;
        $key    = $this->getEncryptKey();
        $cipher = $this->getEncryptCipher();

        $encrypted = openssl_encrypt(
            $value,
            $cipher,
            $key,
            OPENSSL_RAW_DATA
        );

        return base64_encode($encrypted);
    }

    /**
     * 解密字符串
     *
     * @param mixed $value 待解密的值（加密后的 base64 字符串）
     *
     * @return string|null 解密后的值（null 或无法解密时返回 null）
     */
    public function decrypt(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $key    = $this->getEncryptKey();
        $cipher = $this->getEncryptCipher();

        $decoded = base64_decode($value, true);

        if (false === $decoded) {
            return null;
        }

        $decrypted = openssl_decrypt(
            $decoded,
            $cipher,
            $key,
            OPENSSL_RAW_DATA
        );

        return false === $decrypted ? null : $decrypted;
    }

    /**
     * 设置字段值（写入时自动加密）
     *
     * 覆盖 Attribute::setAttr 方法，在写入前对加密字段进行加密处理。
     *
     * @param string $name  属性名
     * @param mixed  $value 属性值
     * @param array  $data  数据
     *
     * @return void
     */
    public function setAttr(string $name, $value, array $data = []): void
    {
        if ($this->isEncryptField($name) && null !== $value) {
            $value = $this->encrypt($value);
        }

        if ($this->parentMethodExists(__FUNCTION__)) {
            /** @phpstan-ignore class.noParent */
            parent::setAttr($name, $value, $data);
        } else {
            $this->data[$name] = $value;
        }
    }

    /**
     * 获取字段值（读取时自动解密）
     *
     * 覆盖 Attribute::getValue 方法，在读取后对加密字段进行解密处理。
     *
     * @param string      $name     字段名称
     * @param mixed       $value    字段值
     * @param bool|string $relation 是否为关联属性或者关联名
     *
     * @return mixed
     */
    protected function getValue(string $name, $value, bool | string $relation = false)
    {
        if ($this->parentMethodExists(__FUNCTION__)) {
            /** @phpstan-ignore class.noParent */
            $value = parent::getValue($name, $value, $relation);
        }

        if ($this->isEncryptField($name) && !$relation) {
            $value = $this->decrypt($value);
        }

        return $value;
    }

    /**
     * 直接设置字段值（绕过获取器/修改器但保留加密）
     *
     * 覆盖 Attribute::set 方法，对加密字段也进行加密处理。
     *
     * @param string $name  属性名
     * @param mixed  $value 值
     *
     * @return void
     */
    public function set(string $name, $value): void
    {
        if ($this->isEncryptField($name) && null !== $value) {
            $value = $this->encrypt($value);
        }

        if ($this->parentMethodExists(__FUNCTION__)) {
            /** @phpstan-ignore class.noParent */
            parent::set($name, $value);
        } else {
            $this->data[$name] = $value;
        }
    }

    /**
     * 查询命名范围：按加密字段的明文值精确匹配（where 加密字段查询）
     *
     * 这是加密后数据唯一可用的数据库层查询方式：先把用户输入加密成密文，
     * 再用密文做精确比较。适用于「按手机号 / 身份证号 / 邮箱精确查找用户」等场景。
     *
     * <code>
     *     // 精确匹配，等同于 where('mobile', '=', User::encryptValue('13800138000'))
     *     $user = User::whereEncrypted('mobile', '13800138000')->find();
     *
     *     // 带操作符
     *     $list = User::whereEncrypted('email', '!=', 'a@b.com')->select();
     *
     *     // IN 查询（批量精确匹配）
     *     $list = User::whereEncrypted('mobile', ['13800138000', '13900139000'])->select();
     *
     *     // NOT IN 查询
     *     $list = User::whereEncrypted('email', 'NOT IN', ['a@b.com', 'c@d.com'])->select();
     * </code>
     *
     * @param \think\db\BaseQuery $query 查询构造器（由 scope 机制自动注入，无需手动传入）
     * @param string              $field 字段名
     * @param mixed               $op    操作符（=, !=, <>, IN, NOT IN 等）；省略时默认 `=`
     * @param mixed               $value 明文值 / 明文值数组
     *
     * @return void
     */
    public function scopeWhereEncrypted($query, string $field, mixed $op, mixed $value = null): void
    {
        // scope 机制会自动把 $query 作为第 1 个参数注入：
        //   用户传 2 个参数 (field, value)         → 实际收到 3 个参数
        //   用户传 3 个参数 (field, op, value)     → 实际收到 4 个参数
        $argc = func_num_args();

        if ($argc === 3) {
            $value = $op;
            $op    = '=';
        }

        $op = strtoupper(trim((string) $op));

        if (is_array($value)) {
            $encrypted = array_map([static::class, 'encryptValue'], $value);

            if ($op === 'NOT IN' || $op === '!=' || $op === '<>') {
                $query->whereNotIn($field, $encrypted);
            } else {
                $query->whereIn($field, $encrypted);
            }

            return;
        }

        $encrypted = static::encryptValue($value);
        $query->where($field, $op, $encrypted);
    }

    /**
     * 在 PHP 层面对查询结果集做加密字段的 LIKE 模糊匹配（whereLike 替代方案）
     *
     * ⚠️  由于 AES 加密的语义安全特性，**在数据库层面无法对密文执行 LIKE '%keyword%'**。
     * 此方法提供一个轻量级替代：先执行 SQL（强烈建议先用非加密字段做前置条件缩小范围），
     * 再在 PHP 内存中对每行记录解密后按 LIKE 模式过滤。
     *
     * 适用场景：结果集规模较小（≤ 几千行）的后台搜索、管理页查询等。
     * 若需要在大量数据上对加密字段做模糊搜索，请改用「盲索引」「分词+加密词库」「数据库层加密函数」等方案。
     *
     * <code>
     *     // 1) 先按普通字段缩小范围，再对 mobile 做加密字段模糊匹配
     *     $rows = User::where('status', 1)
     *                 ->limit(500)
     *                 ->select();
     *
     *     $filtered = User::filterLikeEncrypted($rows, 'mobile', '138%');
     *     // 等价 SQL LIKE: mobile LIKE '138%'（对解密后的明文匹配）
     *
     *     // 2) 按邮箱域名模糊搜索
     *     $rows     = User::limit(1000)->select();
     *     $filtered = User::filterLikeEncrypted($rows, 'email', '%@gmail.com');
     *
     *     // 3) 同时支持 Collection / 数组 / 单条记录对象
     *     $user  = User::find(1);
     *     $match = User::filterLikeEncrypted($user, 'id_card', '%1234');
     * </code>
     *
     * @param iterable|object $rows    查询结果：支持 Collection、数组、单条 Model / 任意有 getAttr 方法或字段属性的对象
     * @param string          $field   加密字段名
     * @param string          $pattern LIKE 模式，支持 `%`（任意字符）和 `_`（单字符）通配符
     *
     * @return array 过滤后的结果（始终返回数组；无匹配时为空数组）
     */
    public static function filterLikeEncrypted(iterable | object $rows, string $field, string $pattern): array
    {
        if (!is_iterable($rows)) {
            $rows = [$rows];
        }

        $regex = static::likePatternToRegex($pattern);
        $out   = [];

        foreach ($rows as $row) {
            if (is_object($row) && method_exists($row, 'getAttr')) {
                $plain = (string) ($row->getAttr($field) ?? '');
            } elseif (is_array($row) && isset($row[$field])) {
                $plain = (string) (static::decryptValue($row[$field]) ?? '');
            } elseif (is_object($row) && isset($row->$field)) {
                $plain = (string) (static::decryptValue($row->$field) ?? '');
            } else {
                $plain = '';
            }

            if (preg_match($regex, $plain)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * 将 SQL LIKE 通配模式转为 PCRE 正则
     *
     * 正确处理通配符和正则元字符的转义顺序：先把 LIKE 通配符换成占位符，
     * 再转义正则元字符，最后把占位符替换回对应的正则语法。
     *
     * @param string $pattern LIKE 模式
     *
     * @return string
     */
    protected static function likePatternToRegex(string $pattern): string
    {
        // 使用不太可能出现在真实 LIKE 模式中的长标记作为占位符
        // 字母和数字不会被 preg_quote 转义，保证两次 str_replace 都精确匹配
        $placeholderPercent    = 'XQZPCTMARKERXQZ';
        $placeholderUnderscore = 'XQZUDWMARKERXQZ';

        $tmp = str_replace(
            ['%', '_'],
            [$placeholderPercent, $placeholderUnderscore],
            $pattern
        );

        $tmp = preg_quote($tmp, '/');

        $regex = str_replace(
            [$placeholderPercent,    $placeholderUnderscore],
            ['.*',                   '.'],
            $tmp
        );

        return '/^' . $regex . '$/us';
    }
}
