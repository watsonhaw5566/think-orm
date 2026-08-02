<?php

declare(strict_types=1);

namespace tests\unit;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCaseBase;
use think\model\Collection as ModelCollection;
use think\Model;

#[Group('unit')]
#[AllowMockObjectsWithoutExpectations]
class ModelCollectionTest extends TestCaseBase
{
    private function createMockModel(int $id, array $data = []): Model
    {
        $fullData = array_merge(['id' => $id], $data);

        $model = $this->getMockBuilder(Model::class)
            ->disableOriginalConstructor()
            ->disableOriginalClone()
            ->onlyMethods(['getPk', 'delete', 'save', 'allowField', 'hidden', 'visible', 'append', 'mapping', 'scene', 'setParent', 'withFieldAttr', 'bindAttr', 'eagerlyResultSet', 'toArray', 'getData', 'getAttr', 'offsetGet', 'offsetExists', '__isset', '__get'])
            ->getMock();

        $model->method('getPk')->willReturn('id');
        $model->method('getData')->willReturnCallback(
            function (?string $name = null) use ($fullData) {
                if (is_null($name)) {
                    return $fullData;
                }

                return $fullData[$name] ?? null;
            }
        );
        $model->method('toArray')->willReturn($fullData);
        $model->method('getAttr')->willReturnCallback(
            function (string $name) use ($fullData) {
                return $fullData[$name] ?? null;
            }
        );
        $model->method('offsetGet')->willReturnCallback(
            function (mixed $name) use ($fullData) {
                return $fullData[$name] ?? null;
            }
        );
        $model->method('offsetExists')->willReturnCallback(
            function (mixed $name) use ($fullData) {
                return isset($fullData[$name]);
            }
        );
        $model->method('__isset')->willReturnCallback(
            function (string $name) use ($fullData) {
                return isset($fullData[$name]);
            }
        );
        $model->method('__get')->willReturnCallback(
            function (string $name) use ($fullData) {
                return $fullData[$name] ?? null;
            }
        );
        $model->method('allowField')->willReturnSelf();

        return $model;
    }

    #[Test]
    public function constructFromArray(): void
    {
        $model1     = $this->createMockModel(1);
        $model2     = $this->createMockModel(2);
        $collection = new ModelCollection([$model1, $model2]);

        $this->assertCount(2, $collection);
        $this->assertSame($model1, $collection[0]);
        $this->assertSame($model2, $collection[1]);
    }

    #[Test]
    public function makeStaticMethod(): void
    {
        $model      = $this->createMockModel(1);
        $collection = ModelCollection::make([$model]);
        $this->assertInstanceOf(ModelCollection::class, $collection);
        $this->assertCount(1, $collection);
    }

    #[Test]
    public function isEmptyCollection(): void
    {
        $empty = new ModelCollection([]);
        $this->assertTrue($empty->isEmpty());

        $nonEmpty = new ModelCollection([$this->createMockModel(1)]);
        $this->assertFalse($nonEmpty->isEmpty());
    }

    #[Test]
    public function loadWithEmptyCollectionDoesNothing(): void
    {
        $collection = new ModelCollection([]);
        $result     = $collection->load(['relation1', 'relation2']);
        $this->assertSame($collection, $result);
    }

    #[Test]
    public function deleteReturnsTrue(): void
    {
        $model1 = $this->createMockModel(1);
        $model1->expects($this->once())->method('delete');

        $model2 = $this->createMockModel(2);
        $model2->expects($this->once())->method('delete');

        $collection = new ModelCollection([$model1, $model2]);
        $result     = $collection->delete();
        $this->assertTrue($result);
    }

    #[Test]
    public function deleteEmptyCollectionReturnsTrue(): void
    {
        $collection = new ModelCollection([]);
        $this->assertTrue($collection->delete());
    }

    #[Test]
    public function updateReturnsTrue(): void
    {
        $data = ['status' => 1];

        $model1 = $this->createMockModel(1);
        $model1->expects($this->once())->method('save')->with($data);

        $model2 = $this->createMockModel(2);
        $model2->expects($this->once())->method('save')->with($data);

        $collection = new ModelCollection([$model1, $model2]);
        $result     = $collection->update($data);
        $this->assertTrue($result);
    }

    #[Test]
    public function updateWithAllowField(): void
    {
        $data       = ['status' => 1, 'name' => 'test'];
        $allowField = ['status'];

        $model1 = $this->createMockModel(1);
        $model1->expects($this->once())->method('allowField')->with($allowField);
        $model1->expects($this->once())->method('save')->with($data);

        $collection = new ModelCollection([$model1]);
        $collection->update($data, $allowField);
    }

    #[Test]
    public function hidden(): void
    {
        $fields = ['password', 'token'];
        $model1 = $this->createMockModel(1);
        $model1->expects($this->once())->method('hidden')->with($fields, false);

        $model2 = $this->createMockModel(2);
        $model2->expects($this->once())->method('hidden')->with($fields, false);

        $collection = new ModelCollection([$model1, $model2]);
        $result     = $collection->hidden($fields);
        $this->assertSame($collection, $result);
    }

    #[Test]
    public function hiddenWithMerge(): void
    {
        $fields = ['password'];
        $model  = $this->createMockModel(1);
        $model->expects($this->once())->method('hidden')->with($fields, true);

        $collection = new ModelCollection([$model]);
        $collection->hidden($fields, true);
    }

    #[Test]
    public function visible(): void
    {
        $fields = ['id', 'name'];
        $model1 = $this->createMockModel(1);
        $model1->expects($this->once())->method('visible')->with($fields, false);

        $collection = new ModelCollection([$model1]);
        $result     = $collection->visible($fields);
        $this->assertSame($collection, $result);
    }

    #[Test]
    public function visibleWithMerge(): void
    {
        $fields = ['email'];
        $model  = $this->createMockModel(1);
        $model->expects($this->once())->method('visible')->with($fields, true);

        $collection = new ModelCollection([$model]);
        $collection->visible($fields, true);
    }

    #[Test]
    public function append(): void
    {
        $fields = ['full_name', 'age'];
        $model  = $this->createMockModel(1);
        $model->expects($this->once())->method('append')->with($fields, false);

        $collection = new ModelCollection([$model]);
        $result     = $collection->append($fields);
        $this->assertSame($collection, $result);
    }

    #[Test]
    public function appendWithMerge(): void
    {
        $fields = ['is_active'];
        $model  = $this->createMockModel(1);
        $model->expects($this->once())->method('append')->with($fields, true);

        $collection = new ModelCollection([$model]);
        $collection->append($fields, true);
    }

    #[Test]
    public function mapping(): void
    {
        $map   = ['user_name' => 'name', 'user_email' => 'email'];
        $model = $this->createMockModel(1);
        $model->expects($this->once())->method('mapping')->with($map);

        $collection = new ModelCollection([$model]);
        $result     = $collection->mapping($map);
        $this->assertSame($collection, $result);
    }

    #[Test]
    public function scene(): void
    {
        $sceneName = 'public';
        $model     = $this->createMockModel(1);
        $model->expects($this->once())->method('scene')->with($sceneName);

        $collection = new ModelCollection([$model]);
        $result     = $collection->scene($sceneName);
        $this->assertSame($collection, $result);
    }

    #[Test]
    public function setParent(): void
    {
        $parent = $this->createMockModel(99);
        $model  = $this->createMockModel(1);
        $model->expects($this->once())->method('setParent')->with($parent);

        $collection = new ModelCollection([$model]);
        $result     = $collection->setParent($parent);
        $this->assertSame($collection, $result);
    }

    #[Test]
    public function withAttr(): void
    {
        $callback = fn ($value) => strtoupper((string) $value);
        $model    = $this->createMockModel(1);
        $model->expects($this->once())->method('withFieldAttr')->with('name', $callback);

        $collection = new ModelCollection([$model]);
        $result     = $collection->withAttr('name', $callback);
        $this->assertSame($collection, $result);
    }

    #[Test]
    public function withAttrArray(): void
    {
        $callbacks = ['name' => fn ($v) => $v, 'email' => fn ($v) => $v];
        $model     = $this->createMockModel(1);
        $model->expects($this->once())->method('withFieldAttr')->with($callbacks, null);

        $collection = new ModelCollection([$model]);
        $collection->withAttr($callbacks);
    }

    #[Test]
    public function bindAttr(): void
    {
        $attrs = ['profile_name' => 'name'];
        $model = $this->createMockModel(1);
        $model->expects($this->once())->method('bindAttr')->with('profile', $attrs);

        $collection = new ModelCollection([$model]);
        $result     = $collection->bindAttr('profile', $attrs);
        $this->assertSame($collection, $result);
    }

    #[Test]
    public function dictionaryWithNoArgsUsesPrimaryKey(): void
    {
        $model1     = $this->createMockModel(1);
        $model2     = $this->createMockModel(2);
        $collection = new ModelCollection([$model1, $model2]);

        $result = $collection->dictionary();
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertSame($model1, $result[1]);
        $this->assertSame($model2, $result[2]);
    }

    #[Test]
    public function dictionaryWithCustomIndexKey(): void
    {
        $model1     = $this->createMockModel(1, ['uuid' => 'abc-123']);
        $model2     = $this->createMockModel(2, ['uuid' => 'def-456']);
        $collection = new ModelCollection([$model1, $model2]);

        $indexKey = 'uuid';
        $result   = $collection->dictionary(null, $indexKey);
        $this->assertArrayHasKey('abc-123', $result);
        $this->assertArrayHasKey('def-456', $result);
    }

    #[Test]
    public function dictionaryWithExplicitItems(): void
    {
        $model1 = $this->createMockModel(10);
        $model2 = $this->createMockModel(20);
        $items  = [$model1, $model2];

        $collection = new ModelCollection([]);
        $result     = $collection->dictionary($items);

        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
    }

    #[Test]
    public function dictionaryWithPaginator(): void
    {
        $model1 = $this->createMockModel(5);
        $model2 = $this->createMockModel(6);

        $paginator = new \think\paginator\driver\Bootstrap([$model1, $model2], 10, 1, 100);

        $col      = new ModelCollection([]);
        $indexKey = null;
        $result   = $col->dictionary($paginator, $indexKey);

        $this->assertArrayHasKey(5, $result);
        $this->assertArrayHasKey(6, $result);
    }

    #[Test]
    public function dictionaryEmptyCollectionReturnsEmpty(): void
    {
        $collection = new ModelCollection([]);
        $result     = $collection->dictionary();
        $this->assertSame([], $result);
    }

    #[Test]
    public function diffEmptyOriginalReturnsGivenItems(): void
    {
        $empty  = new ModelCollection([]);
        $model1 = $this->createMockModel(1);
        $model2 = $this->createMockModel(2);
        $result = $empty->diff([$model1, $model2]);

        $this->assertInstanceOf(ModelCollection::class, $result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function diffWithIndexKey(): void
    {
        $model1 = $this->createMockModel(1);
        $model2 = $this->createMockModel(2);
        $model3 = $this->createMockModel(3);

        $original = new ModelCollection([$model1, $model2]);
        $target   = [$model2, $model3];

        $result = $original->diff($target, 'id');
        $this->assertCount(1, $result);
        $this->assertSame($model1, $result[0]);
    }

    #[Test]
    public function diffWithoutIndexKeyAutoDetectsPkAndDiffs(): void
    {
        $model1 = $this->createMockModel(1);
        $model2 = $this->createMockModel(2);

        $original = new ModelCollection([$model1, $model2]);
        $result   = $original->diff([$model1]);

        $this->assertCount(1, $result);
        $this->assertSame($model2, $result[0]);
    }

    #[Test]
    public function intersectEmptyOriginalReturnsEmptyCollection(): void
    {
        $empty  = new ModelCollection([]);
        $model  = $this->createMockModel(1);
        $result = $empty->intersect([$model]);

        $this->assertInstanceOf(ModelCollection::class, $result);
        $this->assertCount(0, $result);
    }

    #[Test]
    public function intersectWithIndexKey(): void
    {
        $model1 = $this->createMockModel(1);
        $model2 = $this->createMockModel(2);
        $model3 = $this->createMockModel(3);

        $original = new ModelCollection([$model1, $model2, $model3]);
        $target   = [$model1, $model3];

        $result = $original->intersect($target, 'id');
        $this->assertCount(2, $result);
    }

    #[Test]
    public function intersectWithoutIndexKeyAutoDetectsPkAndIntersects(): void
    {
        $model1   = $this->createMockModel(1);
        $model2   = $this->createMockModel(2);
        $original = new ModelCollection([$model1, $model2]);
        $result   = $original->intersect([$model1]);

        $this->assertCount(1, $result);
        $this->assertSame($model1, $result[0]);
    }

    #[Test]
    public function collectionExtendsBaseCollection(): void
    {
        $this->assertTrue(is_subclass_of(ModelCollection::class, \think\Collection::class));
    }

    #[Test]
    public function toArrayAllItems(): void
    {
        $model1     = $this->createMockModel(1, ['name' => 'a']);
        $model2     = $this->createMockModel(2, ['name' => 'b']);
        $collection = new ModelCollection([$model1, $model2]);

        $arr = $collection->toArray();
        $this->assertCount(2, $arr);
        $this->assertSame(1, $arr[0]['id']);
        $this->assertSame(2, $arr[1]['id']);
    }

    #[Test]
    public function allMethod(): void
    {
        $model1     = $this->createMockModel(1);
        $model2     = $this->createMockModel(2);
        $collection = new ModelCollection([$model1, $model2]);

        $all = $collection->all();
        $this->assertSame([$model1, $model2], $all);
    }
}
