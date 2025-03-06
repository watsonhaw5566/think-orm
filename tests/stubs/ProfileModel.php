<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;
use think\model\relation\BelongsTo;
use think\model\concern\SoftDelete;

/**
 * 用户资料模型
 */
class ProfileModel extends Model
{
    use SoftDelete;
    protected $name = 'profile';
    protected $autoWriteTimestamp = true;

    /**
     * 用户
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
