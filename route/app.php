<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP6!';
});

Route::get('hello/:name', 'index/hello');

Route::group('customer', function () {
    Route::get('video/pagination', 'customer/video/pagination');
    Route::get('video/detail', 'customer/video/detail');
    Route::get('video/sentences', 'customer/video/sentences');
    Route::get('word/translate', 'customer/word/translate');
});

Route::group('admin', function () {
    Route::post('file/upload', 'admin/file/upload');
    Route::delete('file/delete/:key', 'admin/file/delete');

    Route::get('video/pagination', 'admin/video/pagination');
    Route::get('video/detail', 'admin/video/detail');
    Route::post('video/create', 'admin/video/create');
    Route::rule('video/update', 'admin/video/update', 'PUT|PATCH');
    Route::delete('video/delete', 'admin/video/delete');
    Route::rule('video/analyze', 'admin/video/analyze', 'GET|POST');
    Route::get('video/sentences', 'admin/video/sentences');
});

Route::group('portal', function () {
    Route::rule('callback/iFlyTekNotify', 'portal/callback/iFlyTekNotify', 'GET|POST');
    Route::rule('callback/iflytek-notify', 'portal/callback/iFlyTekNotify', 'GET|POST');
});
