<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OptimLockTest extends AbstractMigration
{
    public function change(): void
    {
        $this
            ->table('orm_test_optim_lock', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'integer', [
                'identity' => true,
                'signed'   => false,
                'null'     => false,
            ])
            ->addColumn('title', 'string', [
                'limit' => 100,
                'null'  => false,
            ])
            ->addColumn('content', 'text', [
                'null' => true,
            ])
            ->addColumn('lock_version', 'integer', [
                'limit'   => 11,
                'default' => 0,
                'null'    => false,
                'signed'  => false,
            ])
            ->addColumn('version', 'integer', [
                'limit'   => 11,
                'default' => 0,
                'null'    => false,
                'signed'  => false,
            ])
            ->create();
    }
}
