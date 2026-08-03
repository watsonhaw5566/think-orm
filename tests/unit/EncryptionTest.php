<?php

declare(strict_types=1);

namespace tests\unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCaseBase;
use think\Model;
use think\model\concern\Encryption;
use think\db\Raw;
use ReflectionClass;
use stdClass;

/**
 * Encryption trait 单元测试
 */
#[Group('unit')]
class EncryptionTest extends TestCaseBase
{
    /**
     * 创建测试用的模型类
     */
    private function createTestModel(array $fields = ['name', 'phone', 'secret'], ?string $key = 'test-secret-key-123456'): Model
    {
        return new class ($fields, $key) extends Model {
            use Encryption;

            protected $encryptedFields = [];
            protected $encryptKey;
            protected $autoWriteTimestamp = false;
            protected $name               = 'test_model';
            protected $field              = ['id', 'name', 'phone', 'secret', 'title', 'status'];
            protected $readonly           = [];
            protected $disuse             = [];

            public function __construct(array $encryptedFields, ?string $encryptKey)
            {
                $this->encryptedFields = $encryptedFields;
                $this->encryptKey      = $encryptKey;
                parent::__construct([]);
            }
        };
    }

    /**
     * 通过反射调用 protected/private 方法
     */
    private function invokeMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method     = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    // ==================== 配置相关测试 ====================

    #[Test]
    public function testGetEncryptedFieldsReturnsEmptyByDefault(): void
    {
        $model = $this->createTestModel([]);
        $this->assertSame([], $model->getEncryptedFields());
    }

    #[Test]
    public function testGetEncryptedFieldsReturnsConfigured(): void
    {
        $model = $this->createTestModel(['a', 'b', 'c']);
        $this->assertSame(['a', 'b', 'c'], $model->getEncryptedFields());
    }

    #[Test]
    public function testSetEncryptedFieldsDynamically(): void
    {
        $model = $this->createTestModel(['a']);
        $model->setEncryptedFields(['x', 'y']);
        $this->assertSame(['x', 'y'], $model->getEncryptedFields());
    }

    #[Test]
    public function testSetEncryptKeyDynamically(): void
    {
        $model = $this->createTestModel(['name'], 'initial-key');
        $model->setEncryptKey('new-key');

        $result = $this->invokeMethod($model, 'getEncryptKey');
        $this->assertSame('new-key', $result);
    }

    #[Test]
    public function testDefaultEncryptKeyUsedWhenEmpty(): void
    {
        $model  = $this->createTestModel(['name'], null);
        $result = $this->invokeMethod($model, 'getEncryptKey');
        $this->assertSame('think-orm-encrypt-key', $result);
    }

    // ==================== 加密字段判断 ====================

    #[Test]
    public function testIsEncryptedFieldTrue(): void
    {
        $model = $this->createTestModel(['phone', 'name']);
        $this->assertTrue($this->invokeMethod($model, 'isEncryptedField', ['phone']));
        $this->assertTrue($this->invokeMethod($model, 'isEncryptedField', ['name']));
    }

    #[Test]
    public function testIsEncryptedFieldFalse(): void
    {
        $model = $this->createTestModel(['phone']);
        $this->assertFalse($this->invokeMethod($model, 'isEncryptedField', ['id']));
        $this->assertFalse($this->invokeMethod($model, 'isEncryptedField', ['status']));
    }

    // ==================== 加密值识别 ====================

    #[Test]
    public function testIsEncryptedValueWithMarker(): void
    {
        $model  = $this->createTestModel(['a']);
        $result = $this->invokeMethod($model, 'isEncryptedValue', ['ENC:somebase64data']);
        $this->assertTrue($result);
    }

    #[Test]
    public function testIsEncryptedValueWithoutMarker(): void
    {
        $model  = $this->createTestModel(['a']);
        $result = $this->invokeMethod($model, 'isEncryptedValue', ['plain-text']);
        $this->assertFalse($result);
    }

    #[Test]
    public function testIsEncryptedValueWithNull(): void
    {
        $model  = $this->createTestModel(['a']);
        $result = $this->invokeMethod($model, 'isEncryptedValue', [null]);
        $this->assertFalse($result);
    }

    #[Test]
    public function testIsEncryptedValueWithRaw(): void
    {
        $model  = $this->createTestModel(['a']);
        $raw    = new Raw('NOW()');
        $result = $this->invokeMethod($model, 'isEncryptedValue', [$raw]);
        $this->assertFalse($result);
    }

    #[Test]
    public function testIsEncryptedValueWithArray(): void
    {
        $model  = $this->createTestModel(['a']);
        $result = $this->invokeMethod($model, 'isEncryptedValue', [['a', 'b']]);
        $this->assertFalse($result);
    }

    #[Test]
    public function testCustomEncryptMarkerWorks(): void
    {
        $model = new class () extends Model {
            use Encryption;

            protected $encryptedFields    = ['a'];
            protected $encryptMarker      = 'CUSTOM:';
            protected $autoWriteTimestamp = false;
            protected $name               = 'test_model';
            protected $field              = ['id', 'a'];
        };

        $this->assertFalse($this->invokeMethod($model, 'isEncryptedValue', ['ENC:xxx']));
        $this->assertTrue($this->invokeMethod($model, 'isEncryptedValue', ['CUSTOM:xxx']));
    }

    // ==================== 加密/解密基础 ====================

    #[Test]
    public function testEncryptValueProducesMarkerPrefix(): void
    {
        $model   = $this->createTestModel(['name']);
        $encoded = $this->invokeMethod($model, 'encryptValue', ['张三']);
        $this->assertStringStartsWith('ENC:', $encoded);
    }

    #[Test]
    public function testEncryptThenDecryptString(): void
    {
        $model    = $this->createTestModel(['name']);
        $original = '张三-13800138000-测试内容';
        $encoded  = $this->invokeMethod($model, 'encryptValue', [$original]);
        $decoded  = $this->invokeMethod($model, 'decryptValue', [$encoded]);
        $this->assertSame($original, $decoded);
    }

    #[Test]
    public function testEncryptThenDecryptNumericStringPreservesType(): void
    {
        $model    = $this->createTestModel(['phone']);
        $original = '13800138000';
        $encoded  = $this->invokeMethod($model, 'encryptValue', [$original]);
        $decoded  = $this->invokeMethod($model, 'decryptValue', [$encoded]);
        $this->assertSame($original, $decoded);
        $this->assertIsString($decoded);
    }

    #[Test]
    public function testEncryptThenDecryptArray(): void
    {
        $model    = $this->createTestModel(['secret']);
        $original = ['a' => 1, 'b' => ['nested' => '数据']];
        $encoded  = $this->invokeMethod($model, 'encryptValue', [$original]);
        $decoded  = $this->invokeMethod($model, 'decryptValue', [$encoded]);
        $this->assertSame($original, $decoded);
    }

    #[Test]
    public function testEncryptThenDecryptObject(): void
    {
        $model         = $this->createTestModel(['secret']);
        $original      = new stdClass();
        $original->foo = 'bar';
        $original->num = 42;

        $encoded = $this->invokeMethod($model, 'encryptValue', [$original]);
        $decoded = $this->invokeMethod($model, 'decryptValue', [$encoded]);

        $this->assertSame(['foo' => 'bar', 'num' => 42], $decoded);
    }

    #[Test]
    public function testRandomIvProducesDifferentCiphertextSamePlaintext(): void
    {
        $model = $this->createTestModel(['a']);
        $enc1  = $this->invokeMethod($model, 'encryptValue', ['same']);
        $enc2  = $this->invokeMethod($model, 'encryptValue', ['same']);

        $this->assertNotSame($enc1, $enc2);

        $dec1 = $this->invokeMethod($model, 'decryptValue', [$enc1]);
        $dec2 = $this->invokeMethod($model, 'decryptValue', [$enc2]);
        $this->assertSame($dec1, $dec2);
    }

    #[Test]
    public function testDecryptValueReturnsOriginalOnInvalidBase64(): void
    {
        $model  = $this->createTestModel(['a']);
        $result = $this->invokeMethod($model, 'decryptValue', ['ENC:!!!invalid-base64!!!']);
        $this->assertSame('ENC:!!!invalid-base64!!!', $result);
    }

    #[Test]
    public function testDecryptValueReturnsOriginalOnTooShort(): void
    {
        $model  = $this->createTestModel(['a']);
        $short  = 'ENC:' . base64_encode('X');
        $result = $this->invokeMethod($model, 'decryptValue', [$short]);
        $this->assertSame($short, $result);
    }

    #[Test]
    public function testDifferentKeysCannotDecrypt(): void
    {
        $modelA = $this->createTestModel(['a'], 'key-A');
        $modelB = $this->createTestModel(['a'], 'key-B');

        $encoded = $this->invokeMethod($modelA, 'encryptValue', ['secret-data']);
        $decoded = $this->invokeMethod($modelB, 'decryptValue', [$encoded]);

        $this->assertNotSame('secret-data', $decoded);
    }

    // ==================== setAttr / getAttr 集成 ====================

    #[Test]
    public function testSetAttrEncryptsAutomatically(): void
    {
        $model = $this->createTestModel(['phone']);
        $model->setAttr('phone', '13800138000');
        $raw = $model->getData('phone');
        $this->assertIsString($raw);
        $this->assertStringStartsWith('ENC:', $raw);
    }

    #[Test]
    public function testGetAttrDecryptsAutomatically(): void
    {
        $model = $this->createTestModel(['phone']);
        $model->setAttr('phone', '13800138000');
        $decoded = $model->getAttr('phone');
        $this->assertSame('13800138000', $decoded);
    }

    #[Test]
    public function testSetAttrNullNotEncrypted(): void
    {
        $model = $this->createTestModel(['phone']);
        $model->setAttr('phone', null);
        $raw = $model->getData('phone');
        $this->assertNull($raw);
    }

    #[Test]
    public function testSetAttrAlreadyEncryptedValueNotDoubleEncrypted(): void
    {
        $model   = $this->createTestModel(['name']);
        $encoded = $this->invokeMethod($model, 'encryptValue', ['张三']);
        $model->setAttr('name', $encoded);
        $raw = $model->getData('name');

        $decoded = $this->invokeMethod($model, 'decryptValue', [$raw]);
        $this->assertSame('张三', $decoded);
    }

    #[Test]
    public function testNonEncryptedFieldLeftUntouched(): void
    {
        $model = $this->createTestModel(['phone']);
        $model->setAttr('title', '明文标题');
        $raw = $model->getData('title');
        $this->assertSame('明文标题', $raw);
    }

    // ==================== data() 方法集成 ====================

    #[Test]
    public function testDataMethodBulkSetsEncrypts(): void
    {
        $model = $this->createTestModel(['phone', 'name']);
        $model->data(['phone' => '13900139000', 'name' => '李四', 'status' => 1]);

        $phoneRaw = $model->getData('phone');
        $nameRaw  = $model->getData('name');
        $status   = $model->getData('status');

        $this->assertStringStartsWith('ENC:', $phoneRaw);
        $this->assertStringStartsWith('ENC:', $nameRaw);
        $this->assertSame(1, $status);
    }

    #[Test]
    public function testDataMethodBulkSetsDecryptsOnRead(): void
    {
        $model = $this->createTestModel(['phone', 'name']);
        $model->data(['phone' => '13900139000', 'name' => '李四']);

        $this->assertSame('13900139000', $model->getAttr('phone'));
        $this->assertSame('李四', $model->getAttr('name'));
    }

    // ==================== getChangedData 集成 ====================

    #[Test]
    public function testGetChangedDataEncryptsDecryptedFields(): void
    {
        $model = $this->createTestModel(['name']);
        $model->data(['id' => 1, 'name' => '原名称']);
        $model->refreshOrigin();

        $model->setAttr('name', '新名称');
        $changed = $model->getChangedData();

        $this->assertArrayHasKey('name', $changed);
        $this->assertStringStartsWith('ENC:', $changed['name']);

        $decoded = $this->invokeMethod($model, 'decryptValue', [$changed['name']]);
        $this->assertSame('新名称', $decoded);
    }

    // ==================== 魔术方法访问 ====================

    #[Test]
    public function testMagicSetGetWorks(): void
    {
        $model        = $this->createTestModel(['phone']);
        $model->phone = '13700137000';

        $raw = $model->getData('phone');
        $this->assertStringStartsWith('ENC:', $raw);

        $this->assertSame('13700137000', $model->phone);
    }

    // ==================== 加密算法自定义 ====================

    #[Test]
    public function testCustomEncryptMethodWorks(): void
    {
        $model = new class () extends Model {
            use Encryption;

            protected $encryptedFields    = ['a'];
            protected $encryptMethod      = 'AES-128-CBC';
            protected $encryptKey         = '16-byte-key!!!!';
            protected $autoWriteTimestamp = false;
            protected $name               = 'test_model';
            protected $field              = ['id', 'a'];
        };

        $original = 'AES128测试内容';
        $encoded  = $this->invokeMethod($model, 'encryptValue', [$original]);
        $decoded  = $this->invokeMethod($model, 'decryptValue', [$encoded]);
        $this->assertSame($original, $decoded);
        $this->assertStringStartsWith('ENC:', $encoded);
    }
}
