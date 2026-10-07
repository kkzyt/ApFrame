<?php
namespace apphp\Core;

abstract class Controller
{
    protected static $instances = [];
    protected $ApTemplet;

    function __construct() { $this->ApTemplet = new ApTemplet(); }

    public static function instance(): Controller
    {
        $class = static::class;
        if (!isset(self::$instances[$class])) { self::$instances[$class] = new static(); }
        return self::$instances[$class];
    }

    protected function view($view_name, array $data = []): string
    {
        // Child constructors do not have to initialize the template engine.
        if ($this->ApTemplet === null) { $this->ApTemplet = new ApTemplet(); }
        return $this->ApTemplet->show($view_name, $data);
    }

    protected function redirect($url, $status = 302)
    {
        throw new \apphp\Core\Response\Redirect($url, $status);
    }
}
