<?php

namespace app\admin\model;

use app\admin\model\File;
use app\admin\model\ManagerRole;
use think\model\concern\SoftDelete;

class Manager extends BaseModel
{
    use SoftDelete;

    protected $type = [
        'id' => 'integer',
        'last_login_time' => 'timestamp',
    ];

    public function setPasswordAttr($value)
    {
        return md5($value);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function avatar()
    {
        return $this->hasOne(File::class, 'key', 'avatar_key');
    }

    public function createManager($data)
    {
        $this->save($data);

        if (!empty($data['role_keys'])) {
            $this->roles()->saveAll($data['role_keys']);
        }
    }

    public function updateManager($data)
    {
        if (isset($data['password']) && empty($data['password'])) {
            unset($data['password']);
        }

        $this->exists(true)->save($data);

        $dbRoleIds = ManagerRole::where('manager_id', $this->getData('id'))->column('role_id');
        $insertRoleIds = array_diff($data['role_ids'], $dbRoleIds);
        $removeRoleIds = array_diff($dbRoleIds, $data['role_ids']);

        if (!empty($insertRoleIds)) {
            $this->roles()->saveAll($insertRoleIds);
        }

        if (!empty($removeRoleIds)) {
            $this->roles()->detach($removeRoleIds);
        }
    }

    public function deleteManager()
    {
        $this->delete();

        ManagerRole::destroy(function ($query) {
            $query->where('manager_id', $this->getData('id'));
        });
    }
}
