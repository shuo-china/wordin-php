<?php
declare(strict_types=1);

namespace app\listener;

use think\facade\Config;
use app\admin\model\ConfigGroup;
use app\admin\model\ConfigItem;

class InitConfig
{
    /**
     * 按照分组读取系统配置
     * @return array
     */
    protected function getConfigs()
    {
        $result = [];

        $groups = ConfigGroup::select()->toArray();

        foreach ($groups as $g) {
            $data = [];

            $list = ConfigItem::where('group_id', $g['id'])->select();

            foreach ($list as $item) {
                $data[$item['key']] = $item['value'];
            }

            $result[$g['key']] = $data;
        }
        return $result;
    }

    /**
     * 事件监听处理
     */
    public function handle()
    {
        $configs = cache('sys_config');

        if (!$configs) {
            $configs = $this->getConfigs();
            cache('sys_config', $configs);
        }

        Config::set($configs, 'sys');
    }
}
