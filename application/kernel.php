<?php
    return [

        /**
         * 中间件注册
         * 键名为中间件的名字，值为中间件的类名
         * */

        'middleware' => [
            'test' => \app\test\Middleware\CheckTest::class
        ],

        /**
         * 全局中间件注册
         * 直接填写类名即可
         * */
        'global_middleware' => [
            \app\Auth\Middleware\CheckCsrf::class
        ],


    ];
