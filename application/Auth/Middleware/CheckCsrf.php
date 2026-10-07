<?php
namespace app\Auth\Middleware;

use apphp\Core\Middleware;
use apphp\Core\Request;

class CheckCsrf extends Middleware
{
    public function handle(\Closure $next)
    {
        $method = Request::instance()->method();
        if (in_array($method, ['get', 'head', 'options'], true)) {
            $stored = session()->get('csrf_token', 'Auth');
            if (!is_string($stored) || $stored === '') {
                session()->set('csrf_token', bin2hex(random_bytes(32)), 'Auth');
            }
        } else {
            // Read the raw token; never HTML-escape or coerce attacker input.
            $body = Request::instance()->obtain($method);
            $token = is_array($body) ? ($body['csrf_token'] ?? null) : null;
            $token = $token ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            $stored = session()->get('csrf_token', 'Auth');
            if (!is_string($token) || $token === '' || !is_string($stored) || $stored === '' || !hash_equals($stored, $token)) {
                http_response_code(403);
                return false;
            }
        }
        return $next();
    }
}
