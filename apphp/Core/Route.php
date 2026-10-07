<?php
namespace apphp\Core;
use apphp\Core\Route\Feature;
use apphp\Core\Route\Found;
use apphp\Core\Route\Register;
class Route
{
    use Feature, Found, Register;
    protected static $instance;
    protected $route_group = [];
    protected $module;
    protected $controller;
    protected $action;
    protected $param;
    function __construct() { $this->registerInit(); }
    public static function instance() { if (self::$instance === null) { self::$instance = new static(); } return self::$instance; }
    public static function get($key, $handler) { self::instance()->registerRoute($key, $handler, 'get'); }
    public static function post($key, $handler) { self::instance()->registerRoute($key, $handler, 'post'); }
    public static function put($key, $handler) { self::instance()->registerRoute($key, $handler, 'put'); }
    public static function delete($key, $handler) { self::instance()->registerRoute($key, $handler, 'delete'); }
    public static function restful($key, $controller) { return self::instance()->registerRestful($key, $controller); }
    public static function group($config, $function)
    {
        if (!is_array($config) || !$function instanceof \Closure) { throw new \InvalidArgumentException('Invalid route group'); }
        $route = self::instance(); $route->group_stack[] = $config;
        try { return $function(); } finally { array_pop($route->group_stack); }
    }
    public static function run() { return self::instance()->dispatch(); }
    protected function dispatch()
    {
        $this->module = $this->controller = $this->action = $this->param = null;
        $path = $this->getRouteName(); $method = $this->getNowMethod();
        $group = $this->foundHasRoute($path, $method);
        if (!$group && $method === 'head') { $group = $this->foundHasRoute($path, 'get'); }
        if (!$group) {
            $allowed = [];
            foreach (array_keys($this->route_group) as $candidate) { if ($this->foundHasRoute($path, $candidate)) { $allowed[] = strtoupper($candidate); } }
            if (in_array('GET', $allowed, true)) { $allowed[] = 'HEAD'; }
            http_response_code($allowed ? 405 : 404);
            if ($allowed) { header('Allow: ' . implode(', ', $allowed)); }
            return false;
        }
        $handler = $group['route']['function']; $params = $group['param'];
        $next = function() use ($handler, $params) { return $this->invokeHandler($handler, $params); };
        $classes = array_merge($this->global_middleware, $group['route']['middleware']);
        foreach (array_reverse($classes) as $class) {
            $downstream = $next;
            $next = function() use ($class, $downstream) { $middleware = new $class(); return $middleware->handle($downstream); };
        }
        return $next();
    }
    public function getNowMethod() { return strtolower($_SERVER['REQUEST_METHOD'] ?? 'get'); }
    public function getModule() { return $this->module ?? false; }
    public function getController() { return $this->controller ?? false; }
    public function getAction() { return $this->action ?? false; }
    public function getParam() { return $this->param ?? false; }
}
