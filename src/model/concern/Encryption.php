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
declare (strict_types=1);

namespace think\model\concern;

use think\Model;

/**
 * 字段加密
 *
 * @mixin Model
 *
 * @property array  $encryptedFields 【模型中可定义】需要加密的字段列表
 * @property string $encryptKey      【模型中可定义】加密密钥
 * @property string $encryptMethod   【模型中可定义】加密算法，默认 AES-256-CBC
 * @property string $encryptMarker   【模型中可定义】加密值前缀标记，默认 ENC:
 */
trait Encryption
{
    /**
     * 字段解密后的值缓存（避免重复解密）
     * 使用静态属性存储实例映射，避免与模型属性冲突
     * [oid][fieldName] => decryptedValue
     *
     * @var array
     */
    private static array $encryptionDecryptedValues = [];

    /**
     * 设置加密字段
     *
     * @param array $fields
     * @return $this
     */
    public function setEncryptedFields(array $fields): static
    {
        $this->encryptedFields = $fields;

        return $this;
    }

    /**
     * 获取加密字段列表
     *
     * @return array
     */
    public function getEncryptedFields(): array
    {
        return property_exists($this, 'encryptedFields') && isset($this->encryptedFields)
            ? $this->encryptedFields
            : [];
    }

    /**
     * 设置加密密钥
     *
     * @param string $key
     * @return $this
     */
    public function setEncryptKey(string $key): static
    {
        $this->{$this->encryptKeyPropertyName()} = $key;

        return $this;
    }

    /**
     * 返回 encryptKey 属性名，独立成方法方便 phpstan 分析时不报错
     *
     * @return string
     */
    private function encryptKeyPropertyName(): string
    {
        return 'encryptKey';
    }

    /**
     * 获取加密密钥
     *
     * @return string
     */
    protected function getEncryptKey(): string
    {
        if (property_exists($this, 'encryptKey') && !empty($this->encryptKey)) {
            return is_string($this->encryptKey) ? $this->encryptKey : (string)$this->encryptKey;
        }

        return $this->defaultEncryptKey();
    }

    /**
     * 默认加密密钥
     * 可在模型中重写此方法自定义密钥
     *
     * @return string
     */
    protected function defaultEncryptKey(): string
    {
        return 'think-orm-encrypt-key';
    }

    /**
     * 获取加密算法
     *
     * @return string
     */
    protected function getEncryptMethod(): string
    {
        return property_exists($this, 'encryptMethod') && isset($this->encryptMethod)
            ? $this->encryptMethod
            : 'AES-256-CBC';
    }

    /**
     * 获取加密标记前缀
     *
     * @return string
     */
    protected function getEncryptMarker(): string
    {
        return property_exists($this, 'encryptMarker') && isset($this->encryptMarker)
            ? $this->encryptMarker
            : 'ENC:';
    }

    /**
     * 检测字段是否需要加密
     *
     * @param string $field
     * @return bool
     */
    protected function isEncryptedField(string $field): bool
    {
        $field           = $this->getRealFieldName($field);
        $encryptedFields = $this->getEncryptedFields();

        return in_array($field, $encryptedFields, true);
    }

    /**
     * 获取解密值缓存数组引用
     *
     * @return array
     */
    private function &getDecryptedCacheRef(): array
    {
        $oid = spl_object_id($this);
        if (!isset(self::$encryptionDecryptedValues[$oid])) {
            self::$encryptionDecryptedValues[$oid] = [];
        }

        return self::$encryptionDecryptedValues[$oid];
    }

    /**
     * 清理缓存（对象销毁时）
     */
    public function __destruct()
    {
        $oid = spl_object_id($this);
        unset(self::$encryptionDecryptedValues[$oid]);
    }

    /**
     * 通过修改器 设置数据对象值
     * 重写 Attribute::setAttr，在设置属性时自动加密
     *
     * @param string $name 属性名
     * @param mixed $value 属性值
     * @param array $data 数据
     *
     * @return void
     */
    public function setAttr(string $name, mixed $value, array $data = []): void
    {
        if ($this->isEncryptedField($name) && null !== $value && !$this->isEncryptedValue($value)) {
            $realName       = $this->getRealFieldName($name);
            $decryptedCache = &$this->getDecryptedCacheRef();
            $value          = $this->encryptValue($value);
            unset($decryptedCache[$realName]);
        }
        parent::setAttr($name, $value, $data);
    }

    /**
     * 获取经过获取器处理后的数据对象的值
     * 重写 Attribute::getValue，在获取属性时自动解密
     *
     * @param string $name 字段名称
     * @param mixed $value 字段值
     * @param bool|string $relation 是否为关联属性或者关联名
     *
     * @return mixed
     */
    protected function getValue(string $name, $value, bool|string $relation = false): mixed
    {
        if ($this->isEncryptedField($name) && !$relation) {
            $realName       = $this->getRealFieldName($name);
            $decryptedCache = &$this->getDecryptedCacheRef();

            if (isset($decryptedCache[$realName])) {
                $value = $decryptedCache[$realName];
            } elseif ($this->isEncryptedValue($value)) {
                $value                     = $this->decryptValue($value);
                $decryptedCache[$realName] = $value;
            }
        }

        return parent::getValue($name, $value, $relation);
    }

    /**
     * 批量追加数据对象值
     * 重写以支持 data() 方法设置时的兼容处理
     *
     * @param array $data 数据
     * @param bool $set 是否需要进行数据处理
     *
     * @return $this
     */
    public function appendData(array $data, bool $set = false): static
    {
        if (!$set) {
            $encryptedFields = $this->getEncryptedFields();
            $decryptedCache  = &$this->getDecryptedCacheRef();

            foreach ($encryptedFields as $field) {
                $realField = $this->getRealFieldName($field);
                if (isset($data[$realField])) {
                    if (!$this->isEncryptedValue($data[$realField])) {
                        $data[$realField] = $this->encryptValue($data[$realField]);
                        unset($decryptedCache[$realField]);
                    }
                }
            }
        }

        return parent::appendData($data, $set);
    }

    /**
     * 数据保存前加密
     *
     * @return void
     */
    protected function checkData(): void
    {
        $encryptedFields = $this->getEncryptedFields();
        $decryptedCache  = &$this->getDecryptedCacheRef();

        foreach ($encryptedFields as $field) {
            $realField = $this->getRealFieldName($field);
            $rawValue  = $this->getData($realField);
            if (null !== $rawValue) {
                if (!$this->isEncryptedValue($rawValue)) {
                    $encryptedValue = $this->encryptValue($rawValue);
                    $this->set($realField, $encryptedValue);
                    unset($decryptedCache[$realField]);
                }
            }
        }

        parent::checkData();
    }

    /**
     * 数据读取后解密
     *
     * @return void
     */
    protected function checkResult($result): void
    {
        $this->preloadDecryptedCache();

        parent::checkResult($result);
    }

    /**
     * 预加载解密缓存（AfterRead/查询后调用）
     *
     * @return void
     */
    protected function preloadDecryptedCache(): void
    {
        $encryptedFields = $this->getEncryptedFields();
        $decryptedCache  = &$this->getDecryptedCacheRef();

        foreach ($encryptedFields as $field) {
            $realField = $this->getRealFieldName($field);
            $rawValue  = $this->getData($realField);
            if (null !== $rawValue && $this->isEncryptedValue($rawValue)) {
                if (!isset($decryptedCache[$realField])) {
                    $decryptedCache[$realField] = $this->decryptValue($rawValue);
                }
            }
        }
    }

    /**
     * 检测值是否已加密
     *
     * @param mixed $value
     * @return bool
     */
    protected function isEncryptedValue(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $marker = $this->getEncryptMarker();

        return str_starts_with($value, $marker);
    }

    /**
     * 加密单个值
     *
     * @param mixed $value
     * @return string
     */
    protected function encryptValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $method    = $this->getEncryptMethod();
        $key       = $this->getEncryptKey();
        $ivLength  = openssl_cipher_iv_length($method);
        $iv        = openssl_random_pseudo_bytes($ivLength);
        $encrypted = openssl_encrypt($value, $method, $key, OPENSSL_RAW_DATA, $iv);

        $marker = $this->getEncryptMarker();

        return $marker . base64_encode($iv . $encrypted);
    }

    /**
     * 解密单个值
     *
     * @param mixed $value
     * @return mixed
     */
    protected function decryptValue(mixed $value): mixed
    {
        $marker = $this->getEncryptMarker();
        $raw    = substr($value, strlen($marker));
        $data   = base64_decode($raw, true);

        if (false === $data) {
            return $value;
        }

        $method   = $this->getEncryptMethod();
        $key      = $this->getEncryptKey();
        $ivLength = openssl_cipher_iv_length($method);

        if (strlen($data) < $ivLength) {
            return $value;
        }

        $iv        = substr($data, 0, $ivLength);
        $encrypted = substr($data, $ivLength);
        $decrypted = openssl_decrypt($encrypted, $method, $key, OPENSSL_RAW_DATA, $iv);

        if (false === $decrypted) {
            return $value;
        }

        $firstChar = $decrypted[0] ?? '';
        if ($firstChar === '[' || $firstChar === '{') {
            $json = json_decode($decrypted, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $json;
            }
        }

        return $decrypted;
    }

    /**
     * 获取变化的数据 并排除只读数据
     * 重写以保证加密后的字段能正确比较
     *
     * @return array
     */
    public function getChangedData(): array
    {
        $data = parent::getChangedData();

        $encryptedFields = $this->getEncryptedFields();
        $decryptedCache  = &$this->getDecryptedCacheRef();

        foreach ($encryptedFields as $field) {
            $realField = $this->getRealFieldName($field);
            if (array_key_exists($realField, $data)) {
                if (null !== $data[$realField] && !$this->isEncryptedValue($data[$realField])) {
                    $encryptedValue   = $this->encryptValue($data[$realField]);
                    $data[$realField] = $encryptedValue;
                    $this->set($realField, $encryptedValue);
                    unset($decryptedCache[$realField]);
                }
            }
        }

        return $data;
    }
}
