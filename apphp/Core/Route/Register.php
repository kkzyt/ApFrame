<?php
namespace apphp\Core\Route;
use apphp\Core\Request;
trait Register
{
    protected $middleware = [];
    protected $global_middleware = [];
    protected $group_stack = [];
    protected function registerInit()
    {
        $kernel = require APP_PATH . 'kernel.php';
        $this->middleware = $kernel['middleware'] ?? [];
        $this->global_middleware = $kernel['global_middleware'] ?? [];
    }
    protected function resolveMiddleware($names)
    {
        if ($names === null || $names === '') { return []; }
        $result = [];
        foreach (is_array($names) ? $names : [$names] as $name) {
            if (!is_string($name) || $name === '') { throw new \InvalidArgumentException('Invalid middleware'); }
            $class = $this->middleware[$name] ?? $name;
            if (!class_exists($class) || !method_exists($class, 'handle')) { throw new \InvalidArgumentException('Unknown middleware: ' . $name); }
            $result[] = $class;
        }
        return $result;
    }
    protected function registerRoute($key, $function, $group = 'get')
    {
        $options = is_array($function) ? $function : ['function' => $function];
        $function = $options['function'] ?? null;
        if (!$function instanceof \Closure && !is_string($function)) { throw new \InvalidArgumentException('Invalid route handler'); }
        $prefix = []; $middleware = [];
        foreach ($this->group_stack as $config) {
            if (!empty($config['prefix'])) { $prefix[] = trim($config['prefix'], '/'); }
            $middleware = array_merge($middleware, $this->resolveMiddleware($config['middleware'] ?? null));
        }
        $key = $this->removalSlash(implode('/', array_merge($prefix, [trim($key, '/')])));
        $middleware = array_merge($middleware, $this->resolveMiddleware($options['middleware'] ?? null));
        $pattern = ''; $offset = 0; $names = [];
        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)\}/', $key, $tokens, PREG_OFFSET_CAPTURE);
        foreach ($tokens[0] as $index => $token) {
            $name = $tokens[1][$index][0];
            if (in_array($name, $names, true)) { throw new \InvalidArgumentException('Duplicate route parameter: ' . $name); }
            $names[] = $name;
            $pattern .= preg_quote(substr($key, $offset, $token[1] - $offset), '~') . '([^/]+)';
            $offset = $token[1] + strlen($token[0]);
        }
        $pattern .= preg_quote(substr($key, $offset), '~');
        $this->route_group[$group][$key] = ['as' => $options['as'] ?? '', 'middleware' => $middleware,
            'function' => $function, 'pattern' => '~\A' . $pattern . '\z~'];
    }
    protected function registerRestful($key, $controller)
    {
        $options = is_array($controller) ? $controller : ['controller' => $controller];
        $controller = $options['controller'] ?? null;
        if (!is_string($controller) || $controller === '') { throw new \InvalidArgumentException('Missing REST controller'); }
        foreach ([['get','','index'], ['get','/create','create'], ['get','/{id}','show'], ['get','/edit/{id}','edit'],
            ['post','','store'], ['put','/{id}','update'], ['delete','/{id}','delete']] as $route) {
            $this->registerRoute(rtrim($key, '/') . $route[1], ['function' => $controller . '.' . $route[2],
                'middleware' => $options['middleware'] ?? null], $route[0]);
        }
        return true;
    }
    protected function invokeHandler($handler, array $params)
    {
        $this->param = $params;
        if ($handler instanceof \Closure) { return $handler(...$params); }
        $parts = explode('.', $handler);
        if (count($parts) > 3 || in_array('', $parts, true)) { throw new \InvalidArgumentException('Invalid controller handler'); }
        $this->module = $parts[0]; $this->controller = $parts[1] ?? 'Index'; $this->action = $parts[2] ?? 'Index';
        $class = APP_NAMESPACE . '\\' . $this->module . '\\Controller\\' . $this->controller;
        $reflection = new \ReflectionClass($class);
        if (!$reflection->isInstantiable() || !$reflection->hasMethod($this->action)) { throw new \RuntimeException('Controller or action unavailable: ' . $handler); }
        $method = $reflection->getMethod($this->action);
        if (!$method->isPublic() || $method->isConstructor() || $method->isDestructor()) { throw new \RuntimeException('Route action must be public: ' . $handler); }
        $arguments = $method->getParameters();
        if ($arguments) {
            $type = $arguments[0]->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin() && is_a($type->getName(), Request::class, true)) {
                $requestClass = $type->getName(); array_unshift($params, new $requestClass());
            }
        }
        return $method->invokeArgs($this->buildController($class), $params);
    }
    protected function buildController($class, array $stack = [])
    {
        if (in_array($class, $stack, true)) { throw new \RuntimeException('Circular constructor dependency: ' . $class); }
        $stack[] = $class;
        $reflection = new \ReflectionClass($class);
        if (!$reflection->isInstantiable()) { throw new \RuntimeException('Cannot instantiate dependency: ' . $class); }
        $constructor = $reflection->getConstructor();
        if (!$constructor) { return $reflection->newInstance(); }
        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isVariadic()) { break; }
            if ($parameter->isDefaultValueAvailable()) { $args[] = $parameter->getDefaultValue(); continue; }
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $args[] = $this->buildController($type->getName(), $stack);
            } elseif ($parameter->allowsNull() && $type !== null) { $args[] = null; }
            else { throw new \RuntimeException('Cannot resolve constructor parameter: ' . $class . '::$' . $parameter->getName()); }
        }
        return $reflection->newInstanceArgs($args);
    }
}
