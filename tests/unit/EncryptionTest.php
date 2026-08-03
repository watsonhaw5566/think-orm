<?php

declare(strict_types=1);

namespace tests\unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCaseBase;
use think\Model;
use think\model\concern\Encryption;
use ReflectionClass;
use ReflectionObject;

#[Group('unit')]
class EncryptionTest extends TestCaseBase
{
    /**
     * 一个简单的 Encryption trait 宿主类（不继承 Model，用于纯算法测试）
     */
    private function createEncryptionHost(?string $encryptKey = null, string $cipher = 'AES-128-ECB')
    {
        return new class ($encryptKey, $cipher) {
            use Encryption;

            // 注意：属性名必须与 trait 的 resolveEncryptKey / getEncryptKey 读取的名称一致
            protected ?string $encryptKey;
            protected string $encryptCipher;
            protected array $encryptFields = [];

            public function __construct(?string $encryptKey, string $cipher)
            {
                $this->encryptKey    = $encryptKey;
                $this->encryptCipher = $cipher;
            }

            public function withEncryptFields(array $fields): self
            {
                $this->encryptFields = $fields;

                return $this;
            }

            protected function getEncryptFields(): array
            {
                return $this->encryptFields;
            }

            protected function getEncryptKey(): string
            {
                if ($this->encryptKey !== null) {
                    return $this->encryptKey;
                }

                return 'think-orm-default-key';
            }

            protected function getEncryptCipher(): string
            {
                return $this->encryptCipher;
            }

            protected function getRealFieldName(string $name): string
            {
                return $name;
            }
        };
    }

    // =====================  加解密基础测试  =====================

    #[Test]
    public function encryptAndDecryptBasic(): void
    {
        $host = $this->createEncryptionHost('test-secret-key');

        $original  = 'hello world';
        $encrypted = $host->encrypt($original);

        $this->assertNotSame($original, $encrypted);
        $this->assertNotEmpty($encrypted);

        $decrypted = $host->decrypt($encrypted);
        $this->assertSame($original, $decrypted);
    }

    #[Test]
    public function encryptAndDecryptChineseText(): void
    {
        $host = $this->createEncryptionHost('my-secret-2024');

        $original  = '你好，世界！This is a test 123';
        $encrypted = $host->encrypt($original);
        $decrypted = $host->decrypt($encrypted);

        $this->assertSame($original, $decrypted);
    }

    #[Test]
    public function encryptAndDecryptSpecialChars(): void
    {
        $host = $this->createEncryptionHost('special-key');

        $original  = '!@#$%^&*()_+-=[]{}|;:,.<>?';
        $encrypted = $host->encrypt($original);
        $decrypted = $host->decrypt($encrypted);

        $this->assertSame($original, $decrypted);
    }

    #[Test]
    public function encryptNullReturnsNull(): void
    {
        $host   = $this->createEncryptionHost();
        $result = $host->encrypt(null);
        $this->assertNull($result);
    }

    #[Test]
    public function decryptNullReturnsNull(): void
    {
        $host   = $this->createEncryptionHost();
        $result = $host->decrypt(null);
        $this->assertNull($result);
    }

    #[Test]
    public function decryptEmptyStringReturnsEmpty(): void
    {
        $host   = $this->createEncryptionHost();
        $result = $host->decrypt('');
        $this->assertSame('', $result);
    }

    #[Test]
    public function decryptInvalidBase64ReturnsNull(): void
    {
        $host   = $this->createEncryptionHost('secret-key');
        $result = $host->decrypt('!!!invalid-base64!!!');
        $this->assertNull($result);
    }

    #[Test]
    public function decryptWrongCiphertextReturnsNull(): void
    {
        $host   = $this->createEncryptionHost('secret-key');
        $result = $host->decrypt(base64_encode('random-bytes-that-are-not-encrypted'));
        $this->assertNull($result);
    }

    #[Test]
    public function differentKeysProduceDifferentCiphertexts(): void
    {
        $hostA = $this->createEncryptionHost('key-A');
        $hostB = $this->createEncryptionHost('key-B');

        $plaintext = 'sensitive-data';

        $encA = $hostA->encrypt($plaintext);
        $encB = $hostB->encrypt($plaintext);

        $this->assertNotSame($encA, $encB);

        $decAbyA = $hostA->decrypt($encA);
        $decAbyB = $hostB->decrypt($encA);

        $this->assertSame($plaintext, $decAbyA);
        $this->assertNull($decAbyB);
    }

    #[Test]
    public function encryptNumericValue(): void
    {
        $host = $this->createEncryptionHost('num-key');

        $number = 13800138000;
        $enc    = $host->encrypt($number);
        $dec    = $host->decrypt($enc);

        $this->assertSame((string) $number, $dec);
    }

    #[Test]
    public function encryptFloatValue(): void
    {
        $host = $this->createEncryptionHost('float-key');

        $float = 3.1415926;
        $enc   = $host->encrypt($float);
        $dec   = $host->decrypt($enc);

        $this->assertSame((string) $float, $dec);
    }

    #[Test]
    public function sameInputProducesConsistentOutput(): void
    {
        $host = $this->createEncryptionHost('consistent-key');
        $text = 'repeatable';

        $enc1 = $host->encrypt($text);
        $enc2 = $host->encrypt($text);

        $this->assertSame($enc1, $enc2);
    }

    #[Test]
    public function defaultKeyUsedWhenNotConfigured(): void
    {
        $hostA = $this->createEncryptionHost(null);
        $hostB = $this->createEncryptionHost(null);

        $text = 'data-with-default-key';
        $encA = $hostA->encrypt($text);
        $decA = $hostB->decrypt($encA);

        $this->assertSame($text, $decA);
    }

    #[Test]
    public function encryptLongString(): void
    {
        $host = $this->createEncryptionHost('long-string-key');

        $longText = str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', 10);
        $enc      = $host->encrypt($longText);
        $dec      = $host->decrypt($enc);

        $this->assertSame($longText, $dec);
    }

    #[Test]
    public function isEncryptFieldDetectsCorrectly(): void
    {
        $host = $this->createEncryptionHost('field-key')->withEncryptFields(['mobile', 'home_address']);

        $reflection = new ReflectionObject($host);
        $method     = $reflection->getMethod('isEncryptField');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($host, 'mobile'));
        $this->assertTrue($method->invoke($host, 'home_address'));
        $this->assertFalse($method->invoke($host, 'name'));
        $this->assertFalse($method->invoke($host, 'age'));
    }

    #[Test]
    public function customCipherWorks(): void
    {
        if (!in_array('AES-256-ECB', openssl_get_cipher_methods(), true)) {
            $this->markTestSkipped('AES-256-ECB cipher not available');
        }

        $host = $this->createEncryptionHost('256-bit-secret-key!!', 'AES-256-ECB');

        $text = 'cipher-test';
        $enc  = $host->encrypt($text);
        $dec  = $host->decrypt($enc);

        $this->assertSame($text, $dec);
    }

    #[Test]
    public function encryptDecryptRoundTripWithZero(): void
    {
        $host = $this->createEncryptionHost('zero-key');

        $enc = $host->encrypt(0);
        $dec = $host->decrypt($enc);

        $this->assertSame('0', $dec);
    }

    // =====================  静态方法测试  =====================

    #[Test]
    public function staticEncryptValueMatchesInstanceEncrypt(): void
    {
        // 使用类级属性默认值定义的真实类，确保静态 resolveEncryptKey 能读取到
        $host = new EncryptionTestModelA();
        $text = 'static-value-test';

        $byInstance = $host->encrypt($text);
        $byStatic   = EncryptionTestModelA::encryptValue($text);

        $this->assertSame($byInstance, $byStatic);
    }

    #[Test]
    public function staticDecryptValueMatchesInstanceDecrypt(): void
    {
        $host = new EncryptionTestModelB();
        $text = 'decrypt-me';

        $enc  = $host->encrypt($text);
        $decA = $host->decrypt($enc);
        $decB = EncryptionTestModelB::decryptValue($enc);

        $this->assertSame($decA, $decB);
        $this->assertSame($text, $decB);
    }

    #[Test]
    public function staticEncryptValueWithNull(): void
    {
        $this->assertNull(EncryptionTestModelA::encryptValue(null));
    }

    #[Test]
    public function staticDecryptValueWithEmpty(): void
    {
        $this->assertSame('', EncryptionTestModelA::decryptValue(''));
        $this->assertNull(EncryptionTestModelA::decryptValue(null));
    }

    #[Test]
    public function staticMethodsWorkWithDefaultKey(): void
    {
        // EncryptionTestModelC 未配置 encryptKey，会使用默认密钥
        $text = 'default-key-static';

        $enc = EncryptionTestModelC::encryptValue($text);
        $dec = EncryptionTestModelC::decryptValue($enc);

        $this->assertSame($text, $dec);
    }

    // =====================  LIKE 模式转正则测试  =====================

    #[Test]
    public function likePatternPercentPrefix(): void
    {
        $ref = new ReflectionClass(EncryptionTestModelA::class);
        $m   = $ref->getMethod('likePatternToRegex');
        $m->setAccessible(true);

        $regex = $m->invoke(null, '%gmail.com');

        $this->assertMatchesRegularExpression($regex, 'someone@gmail.com');
        $this->assertDoesNotMatchRegularExpression($regex, 'someone@gmail.com.cn');
        // SQL LIKE '%gmail.com' 中 % 可以匹配 0 个字符，所以 gmail.com 自身也匹配
        $this->assertMatchesRegularExpression($regex, 'gmail.com');
    }

    #[Test]
    public function likePatternPercentSuffix(): void
    {
        $ref = new ReflectionClass(EncryptionTestModelA::class);
        $m   = $ref->getMethod('likePatternToRegex');
        $m->setAccessible(true);

        $regex = $m->invoke(null, '138%');

        $this->assertMatchesRegularExpression($regex, '13800138000');
        $this->assertMatchesRegularExpression($regex, '138');
        $this->assertDoesNotMatchRegularExpression($regex, '13900138000');
    }

    #[Test]
    public function likePatternPercentBoth(): void
    {
        $ref = new ReflectionClass(EncryptionTestModelA::class);
        $m   = $ref->getMethod('likePatternToRegex');
        $m->setAccessible(true);

        $regex = $m->invoke(null, '%hello%');

        $this->assertMatchesRegularExpression($regex, 'say hello world');
        $this->assertMatchesRegularExpression($regex, 'hello');
        $this->assertDoesNotMatchRegularExpression($regex, 'hi there');
    }

    #[Test]
    public function likePatternSingleCharWildcard(): void
    {
        $ref = new ReflectionClass(EncryptionTestModelA::class);
        $m   = $ref->getMethod('likePatternToRegex');
        $m->setAccessible(true);

        $regex = $m->invoke(null, 'A_C');

        $this->assertMatchesRegularExpression($regex, 'ABC');
        $this->assertMatchesRegularExpression($regex, 'AxC');
        $this->assertDoesNotMatchRegularExpression($regex, 'AXBC');
        $this->assertDoesNotMatchRegularExpression($regex, 'AC');
    }

    #[Test]
    public function likePatternNoWildcardExact(): void
    {
        $ref = new ReflectionClass(EncryptionTestModelA::class);
        $m   = $ref->getMethod('likePatternToRegex');
        $m->setAccessible(true);

        $regex = $m->invoke(null, 'hello');

        $this->assertMatchesRegularExpression($regex, 'hello');
        $this->assertDoesNotMatchRegularExpression($regex, 'hello world');
        $this->assertDoesNotMatchRegularExpression($regex, 'say hello');
    }

    #[Test]
    public function likePatternRegexCharsAreEscaped(): void
    {
        $ref = new ReflectionClass(EncryptionTestModelA::class);
        $m   = $ref->getMethod('likePatternToRegex');
        $m->setAccessible(true);

        $regex = $m->invoke(null, 'a.b%');

        $this->assertMatchesRegularExpression($regex, 'a.b');
        $this->assertMatchesRegularExpression($regex, 'a.bc');
        $this->assertDoesNotMatchRegularExpression($regex, 'axb');
    }

    // =====================  filterLikeEncrypted 测试  =====================

    #[Test]
    public function filterLikeEncryptedFiltersModelLikeObjects(): void
    {
        $key    = EncryptionTestModelA::ENCRYPT_KEY;
        $cipher = 'AES-128-ECB';

        $plainData = ['13800138000', '13900139000', '13812345678', '15000001111'];

        $makeRow = function (string $mobile) use ($key, $cipher) {
            return new class ($mobile, $key, $cipher) {
                private string $encMobile;
                private string $encryptKey;
                private string $cipher;

                public function __construct(string $plain, string $key, string $cipher)
                {
                    $this->encryptKey = $key;
                    $this->cipher     = $cipher;
                    $enc              = openssl_encrypt($plain, $cipher, $key, OPENSSL_RAW_DATA);
                    $this->encMobile  = base64_encode($enc);
                }

                public function getAttr($name)
                {
                    if ($name === 'mobile') {
                        $decoded = base64_decode($this->encMobile, true);

                        return openssl_decrypt($decoded, $this->cipher, $this->encryptKey, OPENSSL_RAW_DATA);
                    }

                    return null;
                }
            };
        };

        $rows = array_map($makeRow, $plainData);

        $filtered = EncryptionTestModelA::filterLikeEncrypted($rows, 'mobile', '138%');
        $this->assertCount(2, $filtered);

        $mobiles = array_map(fn ($r) => $r->getAttr('mobile'), $filtered);
        $this->assertContains('13800138000', $mobiles);
        $this->assertContains('13812345678', $mobiles);
        $this->assertNotContains('13900139000', $mobiles);
    }

    #[Test]
    public function filterLikeEncryptedWithSingleModelLikeObject(): void
    {
        $key    = EncryptionTestModelB::ENCRYPT_KEY;
        $cipher = 'AES-128-ECB';

        $makeRow = function (string $email) use ($key, $cipher) {
            return new class ($email, $key, $cipher) {
                private string $encEmail;
                private string $encryptKey;
                private string $cipher;

                public function __construct(string $plain, string $key, string $cipher)
                {
                    $this->encryptKey = $key;
                    $this->cipher     = $cipher;
                    $enc              = openssl_encrypt($plain, $cipher, $key, OPENSSL_RAW_DATA);
                    $this->encEmail   = base64_encode($enc);
                }

                public function getAttr($name)
                {
                    if ($name === 'email') {
                        $decoded = base64_decode($this->encEmail, true);

                        return openssl_decrypt($decoded, $this->cipher, $this->encryptKey, OPENSSL_RAW_DATA);
                    }

                    return null;
                }
            };
        };

        $match    = $makeRow('zhangsan@gmail.com');
        $notMatch = $makeRow('lisi@yahoo.com');

        $resultA = EncryptionTestModelB::filterLikeEncrypted($match, 'email', '%@gmail.com');
        $resultB = EncryptionTestModelB::filterLikeEncrypted($notMatch, 'email', '%@gmail.com');

        $this->assertCount(1, $resultA);
        $this->assertCount(0, $resultB);
    }

    #[Test]
    public function filterLikeEncryptedWithArrayRows(): void
    {
        $key    = EncryptionTestModelA::ENCRYPT_KEY;
        $cipher = 'AES-128-ECB';

        $enc = static function (string $v) use ($cipher, $key): string {
            return base64_encode(openssl_encrypt($v, $cipher, $key, OPENSSL_RAW_DATA));
        };

        $rows = [
            ['id' => 1, 'email' => $enc('a@gmail.com')],
            ['id' => 2, 'email' => $enc('b@outlook.com')],
            ['id' => 3, 'email' => $enc('c@gmail.com')],
        ];

        $filtered = EncryptionTestModelA::filterLikeEncrypted($rows, 'email', '%@gmail.com');

        $this->assertCount(2, $filtered);
        $this->assertSame(1, $filtered[0]['id']);
        $this->assertSame(3, $filtered[1]['id']);
    }

    #[Test]
    public function filterLikeEncryptedEmptyResultWhenNoMatch(): void
    {
        $this->assertSame([], EncryptionTestModelC::filterLikeEncrypted([], 'field', '%anything%'));
    }
}

// ========  测试辅助类：在类定义层面就配置好 encryptKey 默认值，用于静态方法测试  ========

/**
 * 测试模型 A：使用固定密钥
 */
class EncryptionTestModelA
{
    use Encryption;

    public const ENCRYPT_KEY = 'static-match-key-0001';

    protected ?string $encryptKey   = self::ENCRYPT_KEY;
    protected string $encryptCipher = 'AES-128-ECB';
    protected array $encryptFields  = ['mobile', 'email'];

    protected function getRealFieldName(string $name): string
    {
        return $name;
    }
}

/**
 * 测试模型 B：使用不同的固定密钥
 */
class EncryptionTestModelB
{
    use Encryption;

    public const ENCRYPT_KEY = 'static-decrypt-key-0002';

    protected ?string $encryptKey   = self::ENCRYPT_KEY;
    protected string $encryptCipher = 'AES-128-ECB';
    protected array $encryptFields  = ['email'];

    protected function getRealFieldName(string $name): string
    {
        return $name;
    }
}

/**
 * 测试模型 C：不配置 encryptKey，将使用默认密钥
 */
class EncryptionTestModelC
{
    use Encryption;

    protected array $encryptFields = [];

    protected function getRealFieldName(string $name): string
    {
        return $name;
    }
}
