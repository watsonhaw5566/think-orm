<?php
declare (strict_types = 1);

namespace tests\orm;

use PHPUnit\Framework\TestCase;
use think\facade\Db;
use think\Model;

class ModelTest extends TestCase
{
    protected static $testData;

    public static function setUpBeforeClass(): void
    {
        Db::execute('DROP TABLE IF EXISTS `test_model`;');
        Db::execute(
            <<<'SQL'
CREATE TABLE `test_model` (
     `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
     `name` varchar(32) NOT NULL,
     `score` int(11) NOT NULL DEFAULT '0',
     `status` tinyint(4) NOT NULL DEFAULT '0',
     `create_time` datetime DEFAULT NULL,
     `update_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL
        );
    }

    public function setUp(): void
    {
        Db::execute('TRUNCATE TABLE `test_model`;');
        self::$testData = [
            ['id' => 1, 'name' => 'test1', 'score' => 35, 'status' => 1],
            ['id' => 2, 'name' => 'test2', 'score' => 25, 'status' => 0],
            ['id' => 3, 'name' => 'test3', 'score' => 40, 'status' => 1],
        ];
    }

    public function testCreate()
    {
        $data   = ['name' => 'test4', 'score' => 45, 'status' => 1];
        $result = TestModel::create($data);

        $this->assertInstanceOf(Model::class, $result);
        $this->assertNotEmpty($result->id);
        $this->assertEquals($data['name'], $result->name);
        $this->assertEquals($data['status'], $result->status);
        $this->assertNotEmpty($result->create_time);
    }

    public function testUpdate()
    {
        Db::table('test_model')->insertAll(self::$testData);

        $model = TestModel::find(1);

        $updateData = ['name' => 'updated', 'score' => 20, 'status' => 0];
        $result     = $model->save($updateData);

        $this->assertTrue($result);
        $this->assertEquals(1, $model->id);
        $this->assertEquals($updateData['name'], $model->name);
        $this->assertEquals($updateData['status'], $model->status);
        $this->assertNotEmpty($model->update_time);
    }

    public function testDelete()
    {
        Db::table('test_model')->insertAll(self::$testData);

        $result = TestModel::destroy(1);
        $this->assertTrue($result);

        $count = Db::table('test_model')->where('id', 1)->count();
        $this->assertEquals(0, $count);
    }

    public function testChangeDetection()
    {
        // 测试新建模型时的变更检测
        $data  = ['name' => 'test5', 'status' => 1];
        $model = new TestModel($data);

        // 测试更新模型时的变更检测
        $model->name = 'updated';
        $this->assertTrue($model->isChange('name'));
        $this->assertFalse($model->isChange('status'));
        $this->assertEquals(['name' => 'updated'], $model->getChangedData());

        // 测试多字段变更
        $model->score = 20;
        $this->assertTrue($model->isChange('name'));
        $this->assertTrue($model->isChange('score'));
        $this->assertEquals(['name' => 'updated', 'score' => 20], $model->getChangedData());
        $model->save();

        // 测试设置相同的值不会触发变更
        $model->name = 'updated';
        $this->assertFalse($model->isChange('name'));
        $this->assertEquals([], $model->getChangedData());
    }

    public function testSave()
    {
        $data   = ['name' => 'test5', 'score' => 35];
        $model  = new TestModel($data);
        $result = $model->save();

        $this->assertTrue($result);
        $this->assertNotEmpty($model->id);
        $this->assertEquals($data['name'], $model->name);
        $this->assertNotEmpty($model->create_time);
    }

    public function testGlobalScope()
    {
        Db::table('test_model')->insertAll(self::$testData);

        $result = TestModel::select();
        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertGreaterThanOrEqual(35, $item->score);
        }
    }

    public function testLocalScope()
    {
        Db::table('test_model')->insertAll(self::$testData);

        // 测试基本查询范围
        $result = TestModel::active()->select();
        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertGreaterThanOrEqual(35, $item->score);
        }

        // 测试带参数的查询范围
        $result = TestModel::nameLike('test1')->select();
        $this->assertCount(1, $result);
        $this->assertEquals('test1', $result[0]->name);

        // 测试组合查询范围
        $result = TestModel::active()->nameLike('test')->select();
        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertGreaterThanOrEqual(30, $item->score);
            $this->assertStringContainsString('test', $item->name);
        }
    }

    public function testRemoveScope()
    {
        Db::table('test_model')->insertAll(self::$testData);

        // 测试移除全局查询范围
        $result = TestModel::withoutGlobalScope()->select();
        $this->assertCount(3, $result);

        // 测试指定移除某个全局查询范围
        $result = TestModel::withoutGlobalScope(['HighScore'])->select();
        $this->assertCount(3, $result);
    }

    public function testIncrement()
    {
        Db::table('test_model')->insertAll(self::$testData);

        // 测试基本自增
        $data = TestModel::find(1);
        $data->inc('score')->save();
        $this->assertEquals(36, $data->score);

        // 测试带步长的自增
        $data = TestModel::find(3);
        $data->inc('score', 5)->save();
        $this->assertEquals(45, $data->score);
    }

    public function testDecrement()
    {
        Db::table('test_model')->insertAll(self::$testData);

        // 测试基本自减
        $data = TestModel::find(1);
        $data->dec('score')->save();
        $this->assertEquals(34, $data->score);

        // 测试带步长的自减
        $data = TestModel::find(3);
        $data->dec('score', 5)->save();
        $this->assertEquals(35, $data->score);
    }

    public function testPaginate()
    {
        Db::table('test_model')->insertAll(self::$testData);

        // 基本分页查询
        $list = Test2Model::paginate();
        $this->assertCount(3, $list->items());
        $this->assertEquals(3, $list->total());
        $this->assertEquals(1, $list->currentPage());
        $this->assertEquals(15, $list->listRows());

        // 自定义每页数量
        $list = Test2Model::paginate(2);
        $this->assertCount(2, $list->items());
        $this->assertEquals(3, $list->total());
        $this->assertEquals(1, $list->currentPage());
        $this->assertEquals(2, $list->listRows());

        // 简单分页
        $list = Test2Model::paginate(2, true);
        $this->assertCount(2, $list->items());
        $this->assertEquals(1, $list->currentPage());
        $this->assertEquals(2, $list->listRows());
        $this->assertTrue($list->hasMore());

        // 条件分页
        $list = Test2Model::where('status', 1)->paginate();
        $this->assertCount(2, $list->items());
        $this->assertEquals(2, $list->total());
        $this->assertEquals(1, $list->currentPage());

        // 作用域分页
        $list = Test2Model::scope('active')->paginate();
        $this->assertCount(2, $list->items());
        $this->assertEquals(2, $list->total());
        $this->assertEquals(1, $list->currentPage());
    }
    public function testAggregate()
    {
        Db::table('test_model')->insertAll(self::$testData);

        // 测试基本聚合
        $count = Test2Model::count();
        $this->assertEquals(3, $count);

        $maxScore = Test2Model::max('score');
        $this->assertEquals(40, $maxScore);

        $minScore = Test2Model::min('score');
        $this->assertEquals(25, $minScore);

        $avgScore = Test2Model::avg('score');
        $this->assertEquals(33.33, round($avgScore, 2));

        $sumScore = Test2Model::sum('score');
        $this->assertEquals(100, $sumScore);

        // 测试条件聚合
        $activeCount = Test2Model::where('status', 1)->count();
        $this->assertEquals(2, $activeCount);

        $highScoreSum = Test2Model::where('score', '>', 30)->sum('score');
        $this->assertEquals(75, $highScoreSum);

        // 测试分组聚合
        $statusCount = Test2Model::group('status')
            ->column('status,COUNT(*) as count,AVG(score) as avg_score');
        $this->assertEquals([
            ['status' => 0, 'count' => 1, 'avg_score' => 25.00],
            ['status' => 1, 'count' => 2, 'avg_score' => 37.50],
        ], array_values($statusCount));

        // 测试作用域聚合
        $activeScoreSum = Test2Model::active()->sum('score');
        $this->assertEquals(75, $activeScoreSum);

        // 测试分组后的筛选
        $groupResult = Test2Model::group('status')
            ->having('COUNT(*) > 1')
            ->column('status,COUNT(*) as count');
        $this->assertEquals([
            ['status' => 1, 'count' => 2],
        ], array_values($groupResult));
    }

    public function testValue()
    {
        Db::table('test_model')->insertAll(self::$testData);

        // 测试基本字段获取器
        $score = Test2Model::where('id', 1)->valueWithAttr('score');
        $this->assertEquals(70, $score); // 原始值35 * 2

        // 测试虚拟获取器
        $statusText = Test2Model::where('id', 1)->valueWithAttr('status');
        $this->assertEquals('启用', $statusText);

        // 测试依赖其他字段的获取器
        $fullName = Test2Model::where('id', 1)->valueWithAttr('name');
        $this->assertEquals('User-test1', $fullName);
    }

    public function testColumn()
    {
        Db::table('test_model')->insertAll(self::$testData);

        // 测试基本字段获取器
        $scores = Test2Model::where('status', 1)->columnWithAttr('score');
        $this->assertEquals([70, 80], array_values($scores)); // 原始值[35, 40] * 2

        // 测试虚拟获取器
        $statusTexts = Test2Model::columnWithAttr('status');
        $this->assertEquals(['启用', '禁用', '启用'], array_values($statusTexts));

        // 测试依赖其他字段的获取器
        $fullNames = Test2Model::columnWithAttr('name');
        $expected  = ['User-test1', 'User-test2', 'User-test3'];
        $this->assertEquals($expected, array_values($fullNames));

        // 测试指定索引字段
        $scores = Test2Model::columnWithAttr('score', 'name');
        $this->assertEquals(['test1' => 70, 'test2' => 50, 'test3' => 80], $scores);
    }
}

class TestModel extends Model
{
    protected $table              = 'test_model';
    protected $autoWriteTimestamp = true;
    protected $globalScope        = ['HighScore'];

    public function scopeHighScore($query)
    {
        return $query->where('score', '>=', 30);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function scopeNameLike($query, $name)
    {
        return $query->where('name', 'like', "%{$name}%");
    }
}
class Test2Model extends Model
{
    protected $table              = 'test_model';
    protected $autoWriteTimestamp = true;

    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    public function getScoreAttr($value)
    {
        return $value * 2;
    }

    public function getStatusAttr($value, $data)
    {
        $status = [0 => '禁用', 1 => '启用'];
        return $status[$data['status']] ?? '未知';
    }

    public function getNameAttr($value, $data)
    {
        return 'User-' . $data['name'];
    }
}
