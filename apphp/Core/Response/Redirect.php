<?php
namespace apphp\Core\Response;

/** A redirect terminates action execution and is handled at the HTTP boundary. */
class Redirect extends \RuntimeException
{
    private $url;
    private $status;
    public function __construct($url, $status = 302)
    {
        if (!is_string($url) || $url === '' || preg_match('/[\r\n\x00]/', $url)) {
            throw new \InvalidArgumentException('Invalid redirect URL');
        }
        if (!in_array($status, [301, 302, 303, 307, 308], true)) {
            throw new \InvalidArgumentException('Invalid redirect status');
        }
        parent::__construct('Redirect');
        $this->url = $url; $this->status = $status;
    }
    public function getUrl() { return $this->url; }
    public function getStatus() { return $this->status; }
}
