<?php
namespace app\wxapp\controller;

use app\wxapp\model\DictType;
use app\wxapp\model\DictItem;

class DictController extends BaseController
{
    public function options()
    {
        $data = cache('dict_options');

        if (!$data) {
            $dictTypeKeys = DictType::where('status', 1)->column('key', 'id');
            $dictItems = DictItem::where('status', 1)->order('id asc')->select()->toArray();
            $data = [];
            foreach ($dictItems as $item) {
                $key = $dictTypeKeys[$item['type_id']];
                $data[$key][] = [
                    'label' => $item['name'],
                    'value' => $item['value'],
                ];
            }
            cache('dict_options', $data);
        }

        $keys = $this->request->param('keys');
        $options = array_intersect_key($data, array_flip(explode(',', $keys)));
        $this->success(200, $options);
    }
}