<?php
declare(strict_types=1);
$dir=__DIR__.'/artifacts'; if (!is_dir($dir)) mkdir($dir,0770,true);
$path=$dir.'/concurrency-'.bin2hex(random_bytes(6)).'.sqlite';
putenv('DB_PATH='.$path);
require dirname(__DIR__).'/bootstrap/app.php';
use Mini\Database as DB;
use App\Services\Business as B;
foreach (glob(BASE_PATH.'/migrations/*.php') as $file) foreach ((require $file)['up'] as $sql) DB::connection()->exec($sql);
require BASE_PATH.'/database/seed.php';
$uid=DB::insert('users',['name'=>'Office','username'=>'office','password'=>'unused','role'=>'office']);
$_SESSION=['user_id'=>$uid];
$customer=DB::insert('customers',['name'=>'Customer','created_by'=>$uid]);
$job=DB::insert('jobs',['customer_id'=>$customer,'title'=>'Race test','assigned_to'=>$uid,'created_by'=>$uid]);
$d=B::saveDocument(['request_key'=>bin2hex(random_bytes(16)),'type'=>'invoice','job_id'=>$job,'date'=>'2026-11-22','lines'=>[['section'=>'General','description'=>'Work','unit'=>'Set','quantity'=>'1','rate'=>1000]]]);
B::documentAction(['id'=>$d['id'],'action'=>'issue']);
$workers=[];
for ($i=0;$i<2;$i++) {
    $process=proc_open([PHP_BINARY,__DIR__.'/payment-worker.php',(string)$job,(string)$d['id'],(string)$uid],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($process)) throw new RuntimeException('Could not start concurrent payment test.');
    fclose($pipes[0]);$workers[]=[$process,$pipes];
}
$results=[];
foreach ($workers as [$process,$pipes]) { $results[]=trim(stream_get_contents($pipes[1])); $errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException($errors); }
sort($results);
if ($results!==['blocked','saved'] || B::paid($d['id'])!==600 || DB::fetchValue('SELECT COUNT(*) FROM payments')!==1) throw new RuntimeException('Concurrent collections exceeded the available balance.');
echo "Passed concurrent payment test: one collection saved, conflicting collection rejected, balance 400 MMK.\n";
