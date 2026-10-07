<?php
namespace apphp\Core\Storage;

class ApSession
{
    protected $driver;
    protected static $info = [];
    private static $session_id;

    function __construct()
    {
        $this->driver = strtolower(SESSION_DRIVER);
        if (!USE_SESSION || self::$session_id !== null) { return; }
        $id = $_COOKIE['apframe_session'] ?? null;
        $valid = is_string($id) && preg_match('/\A(?:[a-f0-9]{32}|[a-f0-9]{64})\z/', $id);
        $data = $valid ? $this->read($id) : null;
        if (!is_string($data)) {
            $id = bin2hex(random_bytes(32));
            $data = serialize([]);
            $this->write($id, $data);
        }
        self::$session_id = $id;
        // Make the ID available during the request that creates the cookie.
        $_COOKIE['apframe_session'] = $id;
        $info = @unserialize($data, ['allowed_classes' => false]);
        self::$info = is_array($info) ? $info : [];
        setcookie('apframe_session', $id, [
            'expires' => time() + 7200, 'path' => '/', 'httponly' => true,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax',
        ]);
    }

    public static function start() { return new static(); }

    private function filePath($id)
    {
        if (!is_string($id) || !preg_match('/\A(?:[a-f0-9]{32}|[a-f0-9]{64})\z/', $id)) {
            throw new \InvalidArgumentException('Invalid session ID');
        }
        $directory = ROOT_PATH . 'runtime/session';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create session directory');
        }
        $directory = realpath($directory);
        $path = $directory . DIRECTORY_SEPARATOR . $id;
        if (is_link($path) || (file_exists($path) && dirname(realpath($path)) !== $directory)) {
            throw new \RuntimeException('Unsafe session file');
        }
        return $path;
    }

    private function redis()
    {
        $redis = new \Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT);
        return $redis;
    }

    private function mysql()
    {
        $mysql = new \mysqli(MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE, MYSQL_PORT);
        $mysql->set_charset(MYSQL_CHARSET);
        return $mysql;
    }

    private function read($id)
    {
        switch ($this->driver) {
            case 'file':
                $path = $this->filePath($id);
                return is_file($path) ? file_get_contents($path) : null;
            case 'redis':
                $redis = $this->redis();
                $data = $redis->get($id);
                if ($data !== false) { $redis->expire($id, 7200); }
                $redis->close();
                return $data;
            case 'mysql':
                $mysql = $this->mysql();
                $stmt = $mysql->prepare('SELECT `value` FROM `session` WHERE `key` = ?');
                $stmt->bind_param('s', $id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close(); $mysql->close();
                return $row['value'] ?? null;
            default: throw new \InvalidArgumentException('Unsupported session driver');
        }
    }

    private function write($id, $data)
    {
        switch ($this->driver) {
            case 'file':
                if (file_put_contents($this->filePath($id), $data, LOCK_EX) === false) {
                    throw new \RuntimeException('Cannot save session');
                }
                break;
            case 'redis':
                $redis = $this->redis();
                if (!$redis->setex($id, 7200, $data)) { throw new \RuntimeException('Cannot save session'); }
                $redis->close(); break;
            case 'mysql':
                $mysql = $this->mysql();
                $stmt = $mysql->prepare('INSERT INTO `session` (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
                $stmt->bind_param('ss', $id, $data);
                if (!$stmt->execute()) { throw new \RuntimeException('Cannot save session'); }
                $stmt->close(); $mysql->close(); break;
            default: throw new \InvalidArgumentException('Unsupported session driver');
        }
    }

    public function set($key, $data, $scope = null)
    {
        if (!USE_SESSION) { throw new \LogicException('Sessions are disabled'); }
        if ($scope === null) { self::$info[$key] = $data; }
        else { self::$info[$scope][$key] = $data; }
        $this->write(self::$session_id, serialize(self::$info));
    }

    public function get($key, $scope = null)
    {
        return $scope === null ? (self::$info[$key] ?? null) : (self::$info[$scope][$key] ?? null);
    }
}
