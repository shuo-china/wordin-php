<?php
declare(strict_types=1);

namespace app\listener;

use think\facade\Config;
use app\admin\model\DictType;
use app\admin\model\DictItem;

class InitDict
{
    /**
     * 按照分组读取系统配置
     * @return array
     */
    protected function getDicts()
    {
        $result = [];

        $types = DictType::select()->toArray();

        foreach ($types as $t) {
            $data = [];

            $list = DictItem::where('type_id', $t['id'])->select()->toArray();
            foreach ($list as $item) {
                $data[$item['value']] = $item['name'];
            }

            $result[$t['key']] = $data;
        }

        return $result;
    }

    /**
     * 事件监听处理
     */
    public function handle()
    {
        $dicts = cache('sys_dict');

        if (!$dicts) {
            $dicts = $this->getDicts();
            cache('sys_dict', $dicts);
        }

        Config::set($dicts, 'dict');
    }
}
