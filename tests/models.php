<?php
$repo=$argv[1]??dirname(__DIR__);
require $repo.'/apphp/Core/Database/Database.php';require $repo.'/apphp/Core/Database/sqlite.php';require $repo.'/apphp/Core/Model.php';
class MemoryDB extends apphp\Core\database\Sqlite {function __construct(){$this->conn=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}}
class Record extends apphp\Core\Model {
    public static $db;
    function __construct(){$this->connection=self::$db;$this->table='users';$this->primary_key='id';}
    function fields($fields){$this->specific_field=$fields;return $this;}
    function one($local=null){return $this->hasOne(ChildRecord::class,'user_id',$local);}
    function many($local=null){return $this->hasMany(ChildRecord::class,'user_id',$local);}
}
class ChildRecord extends Record {function __construct(){parent::__construct();$this->table='children';}}
$checks=0;
function check($actual,$expected,$label){global $checks;if($actual!==$expected){throw new RuntimeException($label.': '.var_export($actual,true));}$checks++;}
function rejects($callback,$label){try{$callback();}catch(InvalidArgumentException $e){check(true,true,$label);return;}throw new RuntimeException($label);}
set_error_handler(function($level,$message){throw new ErrorException($message,0,$level);});
try{
    $db=new MemoryDB();Record::$db=$db;
    $db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    $db->exec('CREATE TABLE children (id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT)');
    $db->insert(['id'=>1,'name'=>'first','email'=>'one'],'users');$db->insert(['id'=>2,'name'=>'second','email'=>'two'],'users');
    foreach([[1,1,'a'],[2,2,'b'],[3,2,'c']] as $row){$db->insert(['id'=>$row[0],'user_id'=>$row[1],'name'=>$row[2]],'children');}
    $m=new Record();check($m->find(1)[0]['id'],'1','First find');check($m->find(2)[0]['id'],'2','Second find resets conditions');
    check($m->id,'2','Found record hydrated');check($m->name,'second','Found attribute');
    check(count($m->limit(1)->get()),1,'Limited query');check(count($m->get()),2,'Limit reset');
    check(array_keys($m->fields(['id'])->get()[0]),['id'],'Field projection');check(array_keys($m->get()[0]),['id','name','email'],'Projection reset');
    $m->name='new-first';check($m->where('id',1)->update(),true,'First update');
    $m->email='new-two';check($m->where('id',2)->update(),true,'Second update');
    $rows=$m->getAll();check($rows[0]['name'],'new-first','First updated');check($rows[1]['name'],'second','Old write fields not reused');check($rows[1]['email'],'new-two','Second updated');
    check($m->missing,null,'Missing property');check($m['missing'],null,'Missing array field');check(isset($m->missing),false,'Missing isset');
    $m['name']='array';check($m->name,'array','Array write visible to property');check($m['name'],'array','Array read');check(isset($m['name']),true,'Array exists');
    unset($m['name']);check($m->name,null,'Array unset');$m->name='property';check($m['name'],'property','Property write visible to array');unset($m->name);check(isset($m['name']),false,'Property unset');
    $m->find(1);$m->name=null;check($m->name,null,'Pending null overrides loaded value');check(isset($m['name']),false,'Null isset');unset($m->name);
    rejects(function()use($m){$m[]='bad';},'Unnamed field rejected');
    $m=new Record();rejects(function()use($m){$m->create();},'Empty create');rejects(function()use($m){$m->update();},'Empty update');
    $m->name='bad';rejects(function()use($m){$m->update();},'Unconditional update');rejects(function()use($m){$m->remove();},'Unconditional delete');
    $m->id=3;$m->name='third';$m->email='three';check($m->create(),true,'Create');rejects(function()use($m){$m->create();},'Pending fields cleared after create');
    $m=new Record();$m->find(2);check($m->one()['name'],'b','hasOne uses actual local id');check(count($m->many()),2,'hasMany returns all');
    $m->find(3);check($m->one(),null,'Empty hasOne');check($m->many(),[],'Empty hasMany');
    $m->external_id=2;check(count($m->many('external_id')),2,'Custom local key');
    try{(new Record())->many();throw new RuntimeException('Missing local key accepted');}catch(LogicException $e){$checks++;}
    $m=new Record();rejects(function()use($m){$m->where('bad;column',1)->get();},'Bad query rejected');check(count($m->get()),3,'Query reset after exception');
    $m->name='bad';rejects(function()use($m){$m->where('bad;column',1)->update();},'Bad update rejected');rejects(function()use($m){$m->update();},'Pending fields reset after exception');
    $m->where('id',3)->remove();check(count($m->get()),2,'Delete resets state');
    echo 'PASS: '.$checks." model checks\n";
}finally{restore_error_handler();}
