<?php
declare(strict_types=1);
putenv('DB_PATH=:memory:');
require dirname(__DIR__).'/bootstrap/app.php';
use Mini\Database as DB;
use App\Services\Business as B;
foreach (glob(BASE_PATH.'/migrations/*.php') as $file) foreach ((require $file)['up'] as $sql) DB::connection()->exec($sql);
function scopeCheck(bool $condition,string $message): void { if (!$condition) throw new RuntimeException($message); }
function scopeDenied(callable $call): void { try{$call();}catch(RuntimeException $e){scopeCheck($e->getCode()===403,'Expected a permission denial.');return;}throw new RuntimeException('Unrelated record was accessible.'); }
$office=DB::insert('users',['name'=>'Office','username'=>'office','password'=>'private','role'=>'office']);
$a=DB::insert('users',['name'=>'Staff A','username'=>'staff-a','password'=>'private','role'=>'staff']);
$b=DB::insert('users',['name'=>'Staff B','username'=>'staff-b','password'=>'private','role'=>'staff']);
$customer=DB::insert('customers',['name'=>'Shared read-only customer','created_by'=>$b]);
$jobA=DB::insert('jobs',['customer_id'=>$customer,'title'=>'A job','assigned_to'=>$a,'created_by'=>$a]);
$jobB=DB::insert('jobs',['customer_id'=>$customer,'title'=>'B job','assigned_to'=>$b,'created_by'=>$b]);
$shared=DB::insert('jobs',['customer_id'=>$customer,'title'=>'A-created job assigned to B','assigned_to'=>$b,'created_by'=>$a]);
$create=function(int $user,int $job,string $description): array {
    $_SESSION=['user_id'=>$user];
    $doc=B::saveDocument(['request_key'=>bin2hex(random_bytes(16)),'type'=>'quotation','job_id'=>$job,'date'=>'2026-11-22','lines'=>[['section'=>'General','description'=>$description,'quantity'=>'1','unit'=>'Set','rate'=>100]]]);
    return B::documentAction(['id'=>$doc['id'],'action'=>'issue']);
};
$docA=$create($a,$jobA,'A work');$docB=$create($b,$jobB,'B work');$sharedA=$create($a,$shared,'A shared work');$sharedB=$create($b,$shared,'B shared work');
$_SESSION=['user_id'=>$a];
scopeCheck(B::document($docA['id'])['creator_username']==='staff-a','Document creator username stored.');
scopeCheck(B::document($sharedA['id'])['id']===$sharedA['id'],'Staff can access their own document on their created job.');
scopeDenied(fn()=>B::job($jobB));scopeDenied(fn()=>B::document($docB['id']));scopeDenied(fn()=>B::document($sharedB['id']));
$ids=DB::fetchAll('SELECT d.id FROM documents d JOIN jobs j ON j.id=d.job_id WHERE '.B::documentScope());
scopeCheck(array_column($ids,'id')===[$docA['id'],$sharedA['id']],'List and detail scopes agree.');
$_SESSION=['user_id'=>$b];scopeCheck(B::document($sharedA['id'])['id']===$sharedA['id'],'Assigned staff can access job documents.');
$child=B::saveDocument(['request_key'=>bin2hex(random_bytes(16)),'type'=>'preliminary','job_id'=>$shared,'source_id'=>$sharedA['id'],'date'=>'2026-11-22','lines'=>[['section'=>'General','description'=>'Converted by B','quantity'=>'1','unit'=>'Set','rate'=>100,'origin_id'=>$sharedA['lines'][0]['id']]]]);
B::audit('viewed','document',$sharedA['id']);
scopeCheck(array_unique(array_column(B::history('document',$sharedA['id']),'user_id'))===[$b],'Staff record history contains only their own operations.');
$_SESSION=['user_id'=>$a];
scopeCheck(!in_array($child['id'],array_column(B::document($sharedA['id'])['related'],'id'),true),'Related-document links cannot expose another staff document.');
DB::update('users',$a,['name'=>'Renamed Staff','username'=>'renamed-a']);
scopeCheck(B::document($docA['id'])['creator_username']==='staff-a','Historical document creator survives account rename.');
$history=B::history('document',$docA['id']);scopeCheck($history[0]['actor_username']==='staff-a','Historical actor username survives account rename.');
B::audit('test_operation','user',$a,['password'=>'sentinel-secret','before'=>['password'=>'sentinel-hash'],'after'=>['name'=>'Visible change']]);
$audit=DB::fetch('SELECT * FROM audit ORDER BY id DESC LIMIT 1');
scopeCheck($audit['actor_username']==='renamed-a','New actions use the current username.');
scopeCheck(!str_contains($audit['detail'],'sentinel'),'Secrets are excluded from history snapshots.');
$_SESSION=['user_id'=>$office];
scopeCheck(count(B::history('document',$sharedA['id']))>=3,'Office can inspect all actors in record history.');
scopeCheck(B::document($docB['id'])['creator_username']==='staff-b','Office can identify creators across staff.');
scopeCheck(DB::fetchValue('PRAGMA integrity_check')==='ok','SQLite integrity.');
echo "Passed staff scope and actor-history checks: related records, scoped links/history, office oversight, immutable creator/actor usernames, and secret redaction.\n";
