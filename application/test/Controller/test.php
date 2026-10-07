<?php
namespace app\test\Controller;
use apphp\Core\Controller;
use apphp\Core\Request;

class test extends Controller
{
    public function index(Request $request, $name)
    {
        return htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
    public function all() { return '南小鸟'; }
}
