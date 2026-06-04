<?php

namespace app\admin\model;

class Video extends BaseModel
{
    public function file()
    {
        return $this->hasOne(File::class, 'key', 'file_key');
    }
}
