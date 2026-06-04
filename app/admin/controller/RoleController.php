<?php

namespace app\admin\controller;

use app\admin\model\Role;

class RoleController extends BaseController
{
    public function options()
    {
        $list = Role::column('name as label,id as value');
        $this->success(200, $list);
    }

    public function pagination()
    {
        $list = Role::paginate();
        $this->success(200, $list);
    }

    public function detail()
    {
        $id = $this->request->param('id');
        $role = Role::find($id);
        $this->success(200, $role);
    }

    public function create()
    {
        $post = $this->request->post();
        Role::create($post);
        $this->success(201);
    }

    public function update()
    {
        $post = $this->request->post();
        Role::update($post);
        $this->success(201);
    }

    public function delete()
    {
        $id = $this->request->param('id');
        Role::destroy($id);
        $this->success(204);
    }
}