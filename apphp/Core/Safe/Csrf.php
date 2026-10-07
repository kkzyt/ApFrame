<?php
namespace apphp\Core\Safe;

class Csrf
{
    protected static $instance;
    public static function instance()
    {
        if (self::$instance === null) { self::$instance = new static(); }
        return self::$instance;
    }

    public function checkCsrf($token)
    {
        if (in_array(strtolower($_SERVER['REQUEST_METHOD']), ['get', 'head', 'options'], true)) { return true; }
        $stored = session()->get('csrf_token', 'Auth');
        return is_string($token) && $token !== '' && is_string($stored) && $stored !== '' && hash_equals($stored, $token);
    }

    public function buildCsrf()
    {
        $token = session()->get('csrf_token', 'Auth');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            session()->set('csrf_token', $token, 'Auth');
        }
        return $token;
    }
}
