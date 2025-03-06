<?php
declare(strict_types=1);

namespace tests\stubs;

use think\Model;
use think\model\relation\BelongsTo;

/**
 * 用户资料模型
 */
class ProfileModel extends Model
{
    protected $name = 'profile';
    protected $autoWriteTimestamp = false;

    /**
     * 用户
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'account_id');
    }
}
