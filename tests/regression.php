<?php
// Run: php tests/regression.php [repository path]
$repo = $argv[1] ?? dirname(__DIR__);
$temp = sys_get_temp_dir() . '/apframe-regression-' . bin2hex(random_bytes(8));
mkdir($temp . '/runtime/session', 0700, true);
mkdir($temp . '/database', 0700, true);
define('ROOT_PATH', $temp . '/');
define('RESOURCE_PATH', $temp . '/');
define('SESSION_DRIVER', 'file');
define('USE_SESSION', true);
define('SQLITE_FILE', ':memory:');
define('DB_FRIEND', false);
require $repo . '/apphp/Core/Database/Database.php';
require $repo . '/apphp/Core/Database/sqlite.php';
require $repo . '/apphp/Core/Database/Mysql.php';
require $repo . '/apphp/Core/Database/PostgreSql.php';
require $repo . '/apphp/Core/Database/MsSql.php';
require $repo . '/apphp/Core/Model.php';
require $repo . '/apphp/Core/Storage/ApSession.php';
require $repo . '/apphp/Core/Request/Obtain.php';
require $repo . '/apphp/Core/Request.php';
require $repo . '/apphp/Core/Middleware.php';
require $repo . '/application/Auth/Middleware/CheckCsrf.php';
require $repo . '/apphp/Core/Safe/Csrf.php';
function session() { return new apphp\Core\Storage\ApSession(); }
$checks = 0;
function check($ok, $message) {
    global $checks;
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
}
function rejects($callback, $message) {
    try { $callback(); } catch (InvalidArgumentException $e) { check(true, $message); return; }
    throw new RuntimeException($message);
}
class TestModel extends apphp\Core\Model {
    function __construct($db) { $this->connection = $db; $this->table = 'users'; $this->primary_key = 'id'; }
    function fields($fields) { $this->specific_field = $fields; return $this; }
}
class TestSqlite extends apphp\Core\database\Sqlite {
    function __construct() { $this->conn = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]); }
}
try {
    // No actual traversal is performed: an invalid cookie must receive a new ID.
    file_put_contents($temp . '/sentinel', 'untouched');
    $_COOKIE['apframe_session'] = '../../sentinel';
    $session = session();
    $id = $_COOKIE['apframe_session'];
    check(preg_match('/\A[a-f0-9]{64}\z/', $id) === 1, 'Traversal cookie must be replaced');
    $session->set('name', "O'Reilly");
    check(file_get_contents($temp . '/sentinel') === 'untouched', 'Outside file remains unchanged');
    check(is_file($temp . '/runtime/session/' . $id), 'First request persists session');
    check(session()->get('name') === "O'Reilly", 'Repeated session instances preserve data');
    $session->set('token', 'scoped', 'Auth');
    check(session()->get('token', 'Auth') === 'scoped', 'Scoped session storage');
    // Simulate another request by clearing request-local static state.
    $reflection = new ReflectionClass(apphp\Core\Storage\ApSession::class);
    $property = $reflection->getProperty('session_id'); $property->setAccessible(true); $property->setValue(null, null);
    $property = $reflection->getProperty('info'); $property->setAccessible(true); $property->setValue(null, []);
    check(session()->get('name') === "O'Reilly", 'Next request reloads session');
    $property = $reflection->getProperty('session_id'); $property->setAccessible(true); $property->setValue(null, null);
    unset($_COOKIE['apframe_session']);
    $session = session();
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = [];
    $middleware = new app\Auth\Middleware\CheckCsrf();
    $called = false;
    check($middleware->handle(function() use (&$called) { $called = true; }) === false && !$called, 'Missing tokens rejected');
    check(http_response_code() === 403, 'Rejected request returns 403');
    $_SERVER['REQUEST_METHOD'] = 'GET';
    check($middleware->handle(function() use (&$called) { $called = true; }) === null && $called, 'Safe request continues');
    $token = session()->get('csrf_token', 'Auth');
    check(is_string($token) && strlen($token) === 64, 'Cryptographic token generated');
    $middleware->handle(function(){});
    check(session()->get('csrf_token', 'Auth') === $token, 'Token stable across safe requests');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    foreach ([null, '', 'wrong', ['array'], 0] as $bad) {
        $_POST = ['csrf_token' => $bad];
        check($middleware->handle(function(){ throw new RuntimeException('Invalid token reached handler'); }) === false, 'Invalid token rejected');
    }
    $_POST = ['csrf_token' => $token]; $called = false;
    check($middleware->handle(function() use (&$called){$called=true;}) === null && $called, 'Valid token accepted');
    $_POST = []; $_SERVER['HTTP_X_CSRF_TOKEN'] = $token;
    check($middleware->handle(function(){}) === null, 'Header token accepted');
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    check(csrfCheck($token), 'Core CSRF uses same session');

    $db = new TestSqlite();
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    $attack = "1' OR '1'='1";
    $db->insert(['id'=>1, 'name'=>"O'Reilly", 'email'=>null], 'users');
    $db->insert(['id'=>2, 'name'=>$attack, 'email'=>'two@example.test'], 'users');
    $db->insert(['id'=>3, 'name'=>'third', 'email'=>'three@example.test'], 'users');
    $rows = (new TestModel($db))->fields(['id','name','email'])->getAll();
    check(count($rows) === 3 && array_keys($rows[0]) === ['id','name','email'], 'Three fields returned separately');
    check(count((new TestModel($db))->where('name', $attack)->get()) === 1, 'Injection string is a literal');
    check((new TestModel($db))->where('id', $attack)->get() === [], 'Injection cannot select all rows');
    check(count((new TestModel($db))->where('id',1)->orWhere('id',3)->get()) === 2, 'OR conditions');
    check(count((new TestModel($db))->where('id',1)->where('name',"O'Reilly")->get()) === 1, 'AND conditions and apostrophes');
    check(count((new TestModel($db))->where('email',null)->get()) === 1, 'Null condition');
    $model = new TestModel($db); $model->name = "updated'"; $model->where('id',2)->update();
    check((new TestModel($db))->find(2)[0]['name'] === "updated'", 'Bound update affects intended row');
    check((new TestModel($db))->where('id',2)->remove() === true, 'Model delete works');
    check(count($db->selectSpecificField('*','users')) === 2, 'Delete leaves other rows intact');
    rejects(function() use ($db) {$db->delete('users');}, 'Delete without conditions rejected');
    rejects(function() use ($db) {$db->update(['name'=>'bad'],'users');}, 'Update without conditions rejected');
    rejects(function() use ($db) {$db->selectSpecificField('*','users', " WHERE 1=1");}, 'Raw conditions rejected');
    rejects(function() use ($db) {$db->selectSpecificField('*','users; DROP TABLE users');}, 'Invalid table rejected');
    rejects(function() use ($db) {$db->selectSpecificField('*','users',null,'1; DROP TABLE users');}, 'Invalid limit rejected');
    check(count($db->selectSpecificField('*','users',null,1,['id'=>'DESC'])) === 1, 'Validated limit and order');
    $db->close();
    echo 'PASS: ' . $checks . " regression checks\n";
} finally {
    // Only files created in this isolated temporary directory are removed.
    foreach (glob($temp . '/runtime/session/*') as $file) { unlink($file); }
    unlink($temp . '/sentinel');
    rmdir($temp . '/runtime/session'); rmdir($temp . '/runtime'); rmdir($temp . '/database'); rmdir($temp);
}
function csrfCheck($token) { return apphp\Core\Safe\Csrf::instance()->checkCsrf($token); }
