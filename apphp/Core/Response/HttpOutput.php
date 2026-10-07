<?php
namespace apphp\Core\Response;

class HttpOutput
{
    /** Keep legacy echo handlers working, while emitting returned content once. */
    public static function send($result)
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') { return; }
        if ($result === null || is_bool($result)) { return; }
        if (is_string($result) || is_int($result) || is_float($result) || (is_object($result) && method_exists($result, '__toString'))) {
            echo $result;
            return;
        }
        throw new \InvalidArgumentException('Controller must return text, a stringable response, or null');
    }

    public static function redirect(Redirect $redirect)
    {
        if (headers_sent()) { throw new \RuntimeException('Cannot redirect after response output'); }
        header('Location: ' . $redirect->getUrl(), true, $redirect->getStatus());
    }
}
