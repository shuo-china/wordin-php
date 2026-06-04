<?php
namespace app\admin\controller;

use app\admin\model\DictType;
use app\admin\model\DictItem;

class DictTypeController extends BaseController
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

    public function list()
    {
        $list = DictType::select();
        $this->success(200, $list);
    }

    public function detail()
    {
        $id = $this->request->param('id');
        $info = DictType::where('id', $id)->find();
        $this->success(200, $info);
    }

    public function create()
    {
        $post = $this->request->post();
        $this->validate($post, 'DictType');

        DictType::create($post);
        cache('sys_dict', null);
        cache('dict_options', null);
        $this->success(201);
    }

    public function update()
    {
        $post = $this->request->post();
        $this->validate($post, 'DictType');

        DictType::update($post);
        cache('sys_dict', null);
        cache('dict_options', null);
        $this->success(201);
    }

    public function delete()
    {
        $id = $this->request->param('id');
        $dictType = DictType::find($id);

        DictItem::where('type_id', $id)->delete();
        $dictType->delete();

        cache('sys_dict', null);
        cache('dict_options', null);
        $this->success(204);
    }
}