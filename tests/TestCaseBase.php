<?php

declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;
use think\db\BaseQuery;
use think\db\ConnectionInterface;
use think\db\connector\Pgsql;
use think\facade\Db;
use think\Model;

/**
 * @property string $connectName;
 */
abstract class TestCaseBase extends TestCase
{
    protected ConnectionInterface $db;
    protected static string $connectName;
    protected static bool $isResetPgScript = false;

    protected static function initModelSupport(): void
    {
        Model::maker(function (Model $model) {
            $model->setConnection(static::$connectName);
        });
    }

    public function __get(string $name)
    {
        if ($name === 'connectName') {
            return static::$connectName;
        }

        throw new \Exception('Undefined property: ' . static::class . '::$' . $name);
    }

    public function setUp(): void
    {
        if (isset(static::$connectName)) {
            $this->db ??= Db::connect(static::$connectName);

            if (static::$connectName === 'pgsql') {
                if (self::$isResetPgScript === false) {
                    pg_reset_function();
                    self::$isResetPgScript = true;
                }
                pg_install_func();
            }
        }
    }

    protected static function compatibleInsertAll(BaseQuery $query, array $data): void
    {
        if ($query->getConnection() instanceof Pgsql) {
            foreach ($data as $datum) {
                (clone $query)->insert($datum);
            }
        } else {
            $query->insertAll($data);
        }
    }

    protected static function compatibleModelInsertAll(Model $query, array $data): void
    {
        if ($query->getConnection() === 'pgsql') {
            foreach ($data as $datum) {
                (clone $query)->insert($datum);
            }
        } else {
            $query->insertAll($data);
        }
    }
}