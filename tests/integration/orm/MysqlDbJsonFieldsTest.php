<?php
declare(strict_types=1);

namespace tests\integration\orm;

class MysqlDbJsonFieldsTest extends DbJsonFieldsBase
{
    protected static string $connectName = 'mysql';
}
