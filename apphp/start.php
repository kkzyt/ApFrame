<?php
/**
 * Created by PhpStorm.
 * User: APOCALYPSE
 * Date: 2017/9/9
 * Time: 18:40
 */

namespace apphp;


define('ROOT_PATH', dirname(__DIR__).'/'); // 网站根路径


// 加载自动初始化文件
require_once "Init.php";
// 框架初始化
$app = Init::initAll();
// 开始执行
$output_level = ob_get_level();
ob_start();
try {
    $result = $app->exec();
    $legacy_output = ob_get_clean();
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') { echo $legacy_output; }
    \apphp\Core\Response\HttpOutput::send($result);
} catch (\apphp\Core\Response\Redirect $redirect) {
    while (ob_get_level() > $output_level) { ob_end_clean(); }
    \apphp\Core\Response\HttpOutput::redirect($redirect);
} catch (\Throwable $error) {
    while (ob_get_level() > $output_level) { ob_end_clean(); }
    throw $error;
}