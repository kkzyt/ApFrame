<?php
namespace apphp\Core\Route;
trait Feature
{
    protected function removalSlash($route) { $route = trim($route, '/'); return $route === '' ? '/' : $route; }
    protected function getRouteName() { return $this->removalSlash(explode('?', $_SERVER['REQUEST_URI'] ?? '/', 2)[0]); }
}
