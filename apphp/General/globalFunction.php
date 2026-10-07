<?php
/**
 * Created by PhpStorm.
 * User: APOCALYPSE
 * Date: 2017/9/10
 * Time: 12:05
 */


/*
 *  全局函数文件
 * */

/*
 * 使用 public 文件夹中的文件
 *
 * $src 文件名
 * */
function asset($src)
{
    $src = $src[0] == '/' ? $src : '/' . $src;

    return $src;
}

/*
 *  快速使用方法获取数据
 *
 *  $request要求
 * */
function obtain($request)
{
    return \apphp\Core\Request::instance()->obtain($request);
}

/*
 *  快速打印值并退出运行
 *
 *  $var 变量
 * */
function dd($var)
{
    /*$type = gettype($var);
    switch ($type)
    {
        case 'int':
        case 'string':
                    echo "${type}(${var})";
                    break;
        case 'array':
                    var_dump($var);
                    break;
        default:
                    var_dump($var);
                    break;
    }
    exit();*/
    dump($var);
    exit();
}

/*
 *  输出 JSON 数据
 *
 *  $info 要格式化的数组
 * */
function json(array $info)
{
    \apphp\Facilitate\Format\Json::echoEncode($info);
}


/*
 *  实例化 Csrf 对象
 * */
function csrf()
{
    return new \apphp\Core\Safe\Csrf();
}

/*
 *  读取目录下 .set 的配置
 * */
function readSet($key, $default = 'undefined')
{
    $config = \apphp\Core\Cache::instance();

    return $config->readConfigKey($key, $default);
}

function one_to_one($to_table, $fk_id, $value)
{
    $db = new \apphp\Core\database\MySql();
    try {
        $rows = $db->selectSpecificField('*', $to_table, ['column' => $fk_id, 'value' => $value], 1);
        return $rows[0] ?? null;
    } finally { $db->close(); }
}

function one_to_many($to_table, $fk_id, $value)
{
    $db = new \apphp\Core\database\MySql();
    try { return $db->selectSpecificField('*', $to_table, ['column' => $fk_id, 'value' => $value]); }
    finally { $db->close(); }
}

function many_to_many($pk_id, $and_table, $to_table, $fk_id, $value)
{
    $mysqli = new mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE);
    foreach ([$pk_id, $and_table, $to_table, $fk_id] as $identifier) {
        if (!is_string($identifier) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $identifier)) {
            throw new \InvalidArgumentException('Invalid SQL identifier');
        }
    }
    $statement = $mysqli->prepare("SELECT * FROM `${and_table}` INNER JOIN `${to_table}` ON `${and_table}`.`${fk_id}` = `${to_table}`.`${fk_id}` WHERE `${and_table}`.`${pk_id}` = ?");
    $statement->bind_param('s', $value);
    $statement->execute();
    $query = $statement->get_result();

    $assoc = $query->fetch_all(MYSQLI_ASSOC);

    $statement->close();
    $mysqli->close();
    return $assoc;
}

function csrf_token()
{
    return session()->get('csrf_token', 'Auth');
}

/**
 * @return \apphp\Core\Response 返回 Response 对象
 * */
function Response()
{
    return new \apphp\Core\Response();
}

/**
 * 快速使用session操作
 * @return \apphp\Core\Storage\ApSession 返回 Session 对象
 * */
function session()
{
    return new \apphp\Core\Storage\ApSession();
}
