<?php

declare(strict_types=1);

namespace tests\integration\orm;

use tests\integration\IntegrationTestCaseBase;
use tests\integration\stubs\OptimLockCustomFieldModel;
use tests\integration\stubs\OptimLockModel;
use think\db\exception\OptimLockException;

abstract class OptimLockTestBase extends IntegrationTestCaseBase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->execute('TRUNCATE TABLE orm_test_optim_lock;');
    }

    public function testCreateRecordWithDefaultLockField(): void
    {
        $model = OptimLockModel::create([
            'title'   => 'Test Title',
            'content' => 'Test Content',
        ]);

        $this->assertNotEmpty($model->getKey());
        $this->assertEquals(0, $model->lock_version);
        $this->assertEquals(0, $model->version);
    }

    public function testUpdateRecordLockVersionIncrement(): void
    {
        $model = OptimLockModel::create([
            'title'   => 'Original Title',
            'content' => 'Original Content',
        ]);
        $id = $model->getKey();

        $this->assertEquals(0, $model->lock_version);

        $model        = OptimLockModel::find($id);
        $model->title = 'Updated Title';
        $result       = $model->save();

        $this->assertTrue($result);
        $this->assertEquals(1, $model->lock_version);

        $fresh = OptimLockModel::find($id);
        $this->assertEquals('Updated Title', $fresh->title);
        $this->assertEquals(1, $fresh->lock_version);
    }

    public function testMultipleUpdatesLockVersionIncrement(): void
    {
        $model = OptimLockModel::create([
            'title'   => 'Title',
            'content' => 'Content',
        ]);
        $id = $model->getKey();

        for ($i = 1; $i <= 5; $i++) {
            $model        = OptimLockModel::find($id);
            $model->title = "Title Update {$i}";
            $model->save();
            $this->assertEquals($i, $model->lock_version);
        }

        $fresh = OptimLockModel::find($id);
        $this->assertEquals(5, $fresh->lock_version);
        $this->assertEquals('Title Update 5', $fresh->title);
    }

    public function testOptimLockConflictOnUpdate(): void
    {
        $this->expectException(OptimLockException::class);

        $model = OptimLockModel::create([
            'title'   => 'Original Title',
            'content' => 'Original Content',
        ]);
        $id = $model->getKey();

        $copy1 = OptimLockModel::find($id);
        $copy2 = OptimLockModel::find($id);

        $copy1->title = 'Updated by Copy 1';
        $copy1->save();

        $copy2->title = 'Updated by Copy 2';
        $copy2->save();
    }

    public function testOptimLockExceptionHasCorrectInfo(): void
    {
        $model = OptimLockModel::create([
            'title'   => 'Test',
            'content' => 'Content',
        ]);
        $id = $model->getKey();

        $copy1 = OptimLockModel::find($id);
        $copy2 = OptimLockModel::find($id);

        $copy1->title = 'First Update';
        $copy1->save();

        try {
            $copy2->title = 'Second Update';
            $copy2->save();
            $this->fail('Expected OptimLockException was not thrown');
        } catch (OptimLockException $e) {
            $this->assertEquals('lock_version', $e->getLockField());
            $this->assertEquals(0, $e->getExpectedVersion());
            $this->assertEquals(0, $e->getAffectedRows());
        }
    }

    public function testForceUpdateLockSkipsCheck(): void
    {
        $model = OptimLockModel::create([
            'title'   => 'Original Title',
            'content' => 'Original Content',
        ]);
        $id = $model->getKey();

        $copy1 = OptimLockModel::find($id);
        $copy2 = OptimLockModel::find($id);

        $copy1->title = 'Updated by Copy 1';
        $copy1->save();
        $this->assertEquals(1, $copy1->lock_version);

        $copy2->title = 'Force Updated by Copy 2';
        $copy2->forceUpdateLock()->save();

        $fresh = OptimLockModel::find($id);
        $this->assertEquals('Force Updated by Copy 2', $fresh->title);
    }

    public function testDeleteRecordWithOptimLock(): void
    {
        $model = OptimLockModel::create([
            'title'   => 'To Delete',
            'content' => 'Will be deleted',
        ]);
        $id = $model->getKey();

        $result = $model->delete();
        $this->assertTrue($result);

        $deleted = OptimLockModel::find($id);
        $this->assertNull($deleted);
    }

    public function testDeleteRecordConflictThrowsException(): void
    {
        $this->expectException(OptimLockException::class);

        $model = OptimLockModel::create([
            'title'   => 'Test Delete',
            'content' => 'Content',
        ]);
        $id = $model->getKey();

        $copy1 = OptimLockModel::find($id);
        $copy2 = OptimLockModel::find($id);

        $copy1->title = 'Updated before delete';
        $copy1->save();

        $copy2->delete();
    }

    public function testForceDeleteSkipsLockCheck(): void
    {
        $model = OptimLockModel::create([
            'title'   => 'Force Delete Test',
            'content' => 'Content',
        ]);
        $id = $model->getKey();

        $copy1 = OptimLockModel::find($id);
        $copy2 = OptimLockModel::find($id);

        $copy1->title = 'Updated by Copy 1';
        $copy1->save();

        $copy2->force()->delete();

        $deleted = OptimLockModel::find($id);
        $this->assertNull($deleted);
    }

    public function testForceUpdateLockForDeleteSkipsCheck(): void
    {
        $model = OptimLockModel::create([
            'title'   => 'Force Update Lock Delete Test',
            'content' => 'Content',
        ]);
        $id = $model->getKey();

        $copy1 = OptimLockModel::find($id);
        $copy2 = OptimLockModel::find($id);

        $copy1->title = 'Updated by Copy 1';
        $copy1->save();

        $copy2->forceUpdateLock()->delete();

        $deleted = OptimLockModel::find($id);
        $this->assertNull($deleted);
    }

    public function testCustomLockField(): void
    {
        $model = OptimLockCustomFieldModel::create([
            'title'   => 'Custom Lock Field',
            'content' => 'Content',
        ]);
        $id = $model->getKey();

        $this->assertEquals(0, $model->version);

        $model        = OptimLockCustomFieldModel::find($id);
        $model->title = 'Updated Title';
        $model->save();

        $this->assertEquals(1, $model->version);

        $fresh = OptimLockCustomFieldModel::find($id);
        $this->assertEquals(1, $fresh->version);
        $this->assertEquals(0, $fresh->lock_version);
    }

    public function testCustomLockFieldConflict(): void
    {
        $this->expectException(OptimLockException::class);

        $model = OptimLockCustomFieldModel::create([
            'title'   => 'Custom Field Conflict',
            'content' => 'Content',
        ]);
        $id = $model->getKey();

        $copy1 = OptimLockCustomFieldModel::find($id);
        $copy2 = OptimLockCustomFieldModel::find($id);

        $copy1->title = 'Copy 1 Update';
        $copy1->save();

        $copy2->title = 'Copy 2 Update';
        $copy2->save();
    }

    public function testUpdateWithNoChanges(): void
    {
        $model = OptimLockModel::create([
            'title'   => 'No Changes',
            'content' => 'Content',
        ]);
        $id = $model->getKey();

        $model  = OptimLockModel::find($id);
        $result = $model->save();

        $this->assertTrue($result);

        $fresh = OptimLockModel::find($id);
        $this->assertEquals(0, $fresh->lock_version);
    }

    public function testConflictRetryScenario(): void
    {
        $model = OptimLockModel::create([
            'title'   => 'Retry Test',
            'content' => 'Initial Content',
        ]);
        $id = $model->getKey();

        $copy1 = OptimLockModel::find($id);
        $copy2 = OptimLockModel::find($id);

        $copy1->content = 'Updated by Copy 1';
        $copy1->save();

        try {
            $copy2->content = 'Attempted Update by Copy 2';
            $copy2->save();
            $this->fail('Expected OptimLockException was not thrown');
        } catch (OptimLockException $e) {
            $copy2->refresh();
            $copy2->content = 'Retried Update by Copy 2';
            $copy2->save();

            $this->assertEquals(2, $copy2->lock_version);
        }

        $fresh = OptimLockModel::find($id);
        $this->assertEquals('Retried Update by Copy 2', $fresh->content);
        $this->assertEquals(2, $fresh->lock_version);
    }
}
