<?php

namespace App;

use Auth;
use App\User;
use Illuminate\Database\Eloquent\Model;

class UserSocialite extends Model
{

    protected $fillable = [
        'unique_id', 'driver', 'name', 'nickname', 'email', 'avatar', 'data',
    ];

    // 查询时自动转换类型
    protected $casts = [
        'data' => 'array',
    ];

    /**
     * data字段转换
     *
     * @param $value
     */
    public function setDataAttribute($value)
    {
        if (!is_string($value)) {
            $this->attributes['data'] = json_encode($value);
        }
    }

    /**
     * 一对多反向关联
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\User');
    }

    /**
     * 社会化登录
     *
     * @param $socialite
     * @param string $driver
     * @return mixed
     */
    public function login($socialite, $driver = 'github')
    {
        // 已关联本地用户
        $row = $this->where('unique_id', $socialite->id)->where('driver', $driver)->first();
        if ($row) {
            // 关联成功，直接登录
            if ($row->user) {
                return Auth::login($row->user, true);
            }

            // 删除无效关联
            $row->delete();
        }

        // 根据当前服务器配置域名生成一个不唯一的邮箱地址
        if (empty($socialite->email)) {
            $socialite->email = implode('@', [bin2hex(random_bytes(5)), ltrim($_SERVER['SERVER_NAME'], '*.')]);
        }

        // 已登录的情况下直接关联
        if (Auth::check()) {
            $user = Auth::user();
        } else {
            // 通过邮箱关联本地用户
            $user = User::where('email', $socialite->email)->first();
            if (!$user) {
                // 创建本地用户
                $user = new User([
                    'name' => $socialite->name,
                    'email' => $socialite->email,
                ]);
                $user->save();
            }
        }

        // 保存第三方登录信息
        $user->socialites()->create([
            'unique_id' => $socialite->id,
            'driver' => $driver,
            'name' => $socialite->name,
            'nickname' => $socialite->nickname,
            'email' => $socialite->email,
            'avatar' => $socialite->avatar,
            'data' => $socialite->user,
        ]);

        return Auth::login($user, true);
    }
}
