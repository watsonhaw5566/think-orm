<?php

declare(strict_types=1);

namespace tests\integration\orm;

class MysqlModelOneToOneTest extends ModelOneToOneBase
{
    protected static string $connectName = 'mysql';
}
