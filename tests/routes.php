<?php
namespace app\probe\Controller {
    class Dependency { public $value = 'dep'; }
    class Demo {
        function value($id) { return 'controller:' . $id; }
        function injected(\apphp\Core\Request $request, $id, $post) { return [$request instanceof \apphp\Core\Request, $id, $post]; }
        private function hidden() { return 'private'; }
        function index() { return 'index'; } function create() { return 'create'; }
        function show($id) { return 'show:' . $id; } function edit($id) { return 'edit:' . $id; }
        function store() { return 'store'; } function update($id) { return 'update:' . $id; }
        function delete($id) { return 'delete:' . $id; }
    }
    class Required { private $dep; function __construct(Dependency $dep) { $this->dep=$dep; } function index() { return $this->dep->value; } }
    class Optional { private $value; function __construct($value='default') {$this->value=$value;} function index(){return $this->value;} }
}
namespace {
    $repo = $argv[1] ?? dirname(__DIR__);
    define('APP_NAMESPACE', 'app');
    require $repo . '/apphp/Core/Request/Obtain.php'; require $repo . '/apphp/Core/Request.php';
    require $repo . '/apphp/Core/Route/Feature.php'; require $repo . '/apphp/Core/Route/Found.php';
    require $repo . '/apphp/Core/Route/Register.php'; require $repo . '/apphp/Core/Route.php';
    class GlobalAround {function handle($next){$GLOBALS['events'][]='global.before';$result=$next();$GLOBALS['events'][]='global.after';return $result;}}
    class GroupAround {function handle($next){$GLOBALS['events'][]='group.before';$result=$next();$GLOBALS['events'][]='group.after';return $result;}}
    class LocalAround {function handle($next){$GLOBALS['events'][]='local.before';$result=$next();$GLOBALS['events'][]='local.after';return $result;}}
    class Stop {function handle($next){return 'blocked';}}
    class Probe extends \apphp\Core\Route {
        function __construct(){$this->middleware=['group'=>GroupAround::class,'local'=>LocalAround::class,'stop'=>Stop::class];}
        function globals($classes){$this->global_middleware=$classes;}
    }
    $r=new Probe();$property=new \ReflectionProperty(\apphp\Core\Route::class,'instance');$property->setAccessible(true);$property->setValue(null,$r);
    $checks=0;
    function check($actual,$expected,$label){global $checks;if($actual!==$expected){throw new \RuntimeException($label.': '.var_export($actual,true));}$checks++;}
    function run($url,$method='GET'){$_SERVER['REQUEST_URI']=$url;$_SERVER['REQUEST_METHOD']=$method;http_response_code(200);return \apphp\Core\Route::run();}
    \apphp\Core\Route::get('/',function(){return 'home';});
    check(run('/?x=1'),'home','root query');
    \apphp\Core\Route::get('/users/{id}/posts/{post}',function($id,$post){return [$id,$post];});
    check(run('/users/1/posts/2'),['1','2'],'multiple params');
    check(run('/users/1/posts/'),false,'empty parameter');
    \apphp\Core\Route::get('/users/{id}',function($id){return $id;});
    \apphp\Core\Route::get('/users/create',function(){return 'static';});
    check(run('/users/create'),'static','static priority');
    check(run('/users/a%20b/?x=1'),'a b','decode and trailing slash');
    check(run('/users/'),false,'missing id');
    \apphp\Core\Route::get('/abc/{id}',function($id){return $id;});
    check(run('/a.c/1'),false,'request regex characters literal');
    \apphp\Core\Route::get('/a.b/{id}',function($id){return $id;});
    check(run('/axb/1'),false,'registered regex characters literal');
    check(run('/a.b/7'),'7','registered literal dot');
    \apphp\Core\Route::get('/controller/{id}','probe.Demo.value');
    check(run('/controller/7'),'controller:7','constructorless controller return');
    check($r->getModule(),'probe','module metadata');check($r->getParam(),['7'],'parameter metadata');
    \apphp\Core\Route::get('/injected/{id}/{post}','probe.Demo.injected');
    check(run('/injected/1/2'),[true,'1','2'],'request injection preserves all params');
    \apphp\Core\Route::get('/dependency','probe.Required.index');check(run('/dependency'),'dep','constructor dependency');
    \apphp\Core\Route::get('/optional','probe.Optional.index');check(run('/optional'),'default','constructor default');
    \apphp\Core\Route::restful('/resource','probe.Demo');
    foreach([['/resource','GET','index'],['/resource/create','GET','create'],['/resource/7','GET','show:7'],
        ['/resource/edit/7','GET','edit:7'],['/resource','POST','store'],['/resource/7','PUT','update:7'],['/resource/7','DELETE','delete:7']] as $case){check(run($case[0],$case[1]),$case[2],'REST '.$case[2]);}
    $r->globals([GlobalAround::class]);$GLOBALS['events']=[];
    \apphp\Core\Route::group(['prefix'=>'admin','middleware'=>'group'],function(){
        \apphp\Core\Route::group(['prefix'=>'v1'],function(){
            \apphp\Core\Route::get('/settings',['middleware'=>'local','function'=>function(){$GLOBALS['events'][]='action';return 'result';}]);
        });
    });
    check(run('/admin/v1/settings'),'result','nested group result');
    check($GLOBALS['events'],['global.before','group.before','local.before','action','local.after','group.after','global.after'],'middleware nesting');
    check(run('/settings'),false,'no leaked unprefixed group');
    try{\apphp\Core\Route::group(['prefix'=>'leak'],function(){throw new \RuntimeException('probe');});}catch(\RuntimeException $e){}
    \apphp\Core\Route::get('/outside',function(){return 'outside';});check(run('/outside'),'outside','group exception cleanup');
    check($r->getModule(),false,'metadata reset');
    \apphp\Core\Route::get('/blocked',['middleware'=>'stop','function'=>function(){throw new \RuntimeException('must not run');}]);
    check(run('/blocked'),'blocked','middleware short circuit');
    check(run('/missing'),false,'404 result');check(http_response_code(),404,'404 status');
    check(run('/resource','DELETE'),false,'405 result');check(http_response_code(),405,'405 status');
    check(run('/outside','HEAD'),'outside','HEAD fallback');
    \apphp\Core\Route::get('/private','probe.Demo.hidden');
    try{run('/private');throw new \LogicException('private action accepted');}catch(\RuntimeException $e){$checks++;}
    echo 'PASS: '.$checks." routing checks\n";
}
