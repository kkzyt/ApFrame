<?php
$repo = $argv[1] ?? dirname(__DIR__);
if (!preg_match('~^(?:[A-Za-z]:[\\\\/]|/)~', $repo)) { $repo = getcwd() . '/' . $repo; }
$temp = sys_get_temp_dir() . '/apframe-controller-' . bin2hex(random_bytes(8));
mkdir($temp . '/resource/view', 0700, true);
define('ROOT_PATH', $temp . '/');
require $repo . '/apphp/Core/ApTemplet.php';
require $repo . '/apphp/Core/Controller.php';
require $repo . '/apphp/Core/Response/Redirect.php';
require $repo . '/apphp/Core/Response/HttpOutput.php';
class First extends apphp\Core\Controller {
    function render($view, $data = []) { return $this->view($view, $data); }
    function go() { $this->redirect('/destination', 303); throw new RuntimeException('Continued after redirect'); }
}
class Second extends apphp\Core\Controller {}
class CustomConstructor extends First { function __construct() {} }
$checks = 0;
function check($actual, $expected, $message) {
    global $checks;
    if ($actual !== $expected) { throw new RuntimeException($message . ': ' . var_export($actual, true)); }
    $checks++;
}
function rejects($callback, $message) {
    try { $callback(); } catch (InvalidArgumentException $e) { check(true,true,$message); return; }
    throw new RuntimeException($message);
}
try {
    $a = First::instance(); $b = Second::instance();
    check(get_class($a), First::class, 'First instance');
    check(get_class($b), Second::class, 'Second instance');
    check(First::instance() === $a, true, 'Same class reuses its instance');
    file_put_contents($temp . '/resource/view/hello.html', 'Hello {{ $name }}');
    file_put_contents($temp . '/resource/view/other.html', 'Other');
    ob_start(); $result = $a->render('hello',['name'=>'<b>A</b>']); $leak = ob_get_clean();
    check($leak, '', 'Rendering emits no output');
    check($result, 'Hello &lt;b&gt;A&lt;/b&gt;', 'Returned HTML escaped');
    $cache = glob($temp . '/runtime/cache/*')[0];
    touch($cache, time() - 50); touch($temp . '/resource/view/hello.html', time() - 100);
    clearstatcache(); $mtime = filemtime($cache);
    check($a->render('hello',['name'=>'B']), 'Hello B', 'Cached render with fresh data');
    clearstatcache(); check(filemtime($cache),$mtime,'Fresh cache not rewritten');
    check($a->render('hello',['name'=>'C']), 'Hello C', 'Repeated cached include works');
    file_put_contents($temp . '/resource/view/hello.html', 'Updated {{ $name }}');
    touch($temp . '/resource/view/hello.html', time());clearstatcache();
    check($a->render('hello',['name'=>'D']), 'Updated D', 'Source changes recompile');
    check($a->render('hello',['name'=>'E','file'=>$temp.'/resource/view/other.html',
        'template_file'=>$temp.'/resource/view/other.html','template_data'=>[],'cache_file'=>'bad','result'=>'bad']),
        'Updated E','Data cannot override file or renderer locals');
    check((new CustomConstructor())->render('other'), 'Other','Child constructor supports view');
    rejects(function() use ($a){$a->render('../other');},'Traversal view rejected');
    try{$a->render('missing');throw new LogicException('Missing template accepted');}catch(RuntimeException $e){$checks++;}
    file_put_contents($temp.'/resource/view/broken.html','<?php echo "partial"; throw new RuntimeException("template error"); ?>');
    $level = ob_get_level();
    try{$a->render('broken');throw new LogicException('Broken template accepted');}catch(RuntimeException $e){}
    check(ob_get_level(),$level,'Buffer cleaned after template error');
    try{$a->go();throw new LogicException('Redirect did not stop');}catch(apphp\Core\Response\Redirect $redirect){
        check($redirect->getUrl(),'/destination','Redirect URL');check($redirect->getStatus(),303,'Redirect status');
    }
    rejects(function(){new apphp\Core\Response\Redirect("/bad\r\nX: injected");},'Header injection rejected');
    rejects(function(){new apphp\Core\Response\Redirect('/ok',200);},'Invalid redirect status rejected');
    $_SERVER['REQUEST_METHOD']='GET';
    ob_start();apphp\Core\Response\HttpOutput::send('response');$body=ob_get_clean();check($body,'response','Returned content sent');
    ob_start();apphp\Core\Response\HttpOutput::send(true);apphp\Core\Response\HttpOutput::send(null);$body=ob_get_clean();check($body,'','No boolean sentinel output');
    $_SERVER['REQUEST_METHOD']='HEAD';ob_start();apphp\Core\Response\HttpOutput::send('hidden');$body=ob_get_clean();check($body,'','HEAD body suppressed');

    // Exercise the actual entry file with a fake app in a separate process.
    mkdir($temp.'/apphp');copy($repo.'/apphp/start.php',$temp.'/apphp/start.php');
    $core = var_export($repo.'/apphp/Core/Response/',true);
    file_put_contents($temp.'/apphp/Init.php','<?php namespace apphp; require '.$core.'."Redirect.php"; require '.$core.'."HttpOutput.php"; class Init {static function initAll(){return new static();} function exec(){ $mode=getenv("APFRAME_TEST_MODE"); if($mode==="redirect"){ echo "discard"; throw new \\apphp\\Core\\Response\\Redirect("/target",303); } echo "legacy:"; return "returned"; }}');
    foreach ([['GET','normal','legacy:returned'],['HEAD','normal',''],['GET','redirect','STATUS:303']] as $case) {
        $runner='<?php $_SERVER["REQUEST_METHOD"]='.var_export($case[0],true).'; putenv("APFRAME_TEST_MODE='.$case[1].'"); require '.var_export($temp.'/apphp/start.php',true).'; '.($case[1]==='redirect'?'echo "STATUS:".http_response_code();':'');
        file_put_contents($temp.'/runner.php',$runner);
        $process=proc_open([PHP_BINARY,$temp.'/runner.php'],[1=>['pipe','w'],2=>['pipe','w']],$pipes,$temp.'/apphp');
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        check(proc_close($process),0,'Entry process succeeds: '.$stderr);check($stderr,'','Entry has no warnings');check($stdout,$case[2],'Entry behavior '.$case[1].' '.$case[0]);
    }
    echo 'PASS: '.$checks." controller checks\n";
} finally {
    $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($iterator as $item){if($item->isDir()){rmdir($item->getPathname());}else{unlink($item->getPathname());}}
    rmdir($temp);
}
