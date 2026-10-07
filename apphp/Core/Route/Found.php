<?php
namespace apphp\Core\Route;
trait Found
{
    protected function foundHasRoute($url, $method_group)
    {
        $routes = $this->route_group[$method_group] ?? [];
        if (isset($routes[$url])) { return ['route' => $routes[$url], 'param' => []]; }
        foreach ($routes as $route) {
            if (preg_match($route['pattern'], $url, $matches)) {
                array_shift($matches);
                return ['route' => $route, 'param' => array_map('rawurldecode', $matches)];
            }
        }
        return false;
    }
}
