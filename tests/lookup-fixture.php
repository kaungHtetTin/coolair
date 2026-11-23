<?php
declare(strict_types=1);
// Only the isolated browser-test database may receive these large-list fixtures.
$path=str_replace('\\','/',(string)getenv('DB_PATH'));
if (!str_contains($path,'/tests/artifacts/')) throw new RuntimeException('An isolated test database is required.');
require dirname(__DIR__).'/bootstrap/app.php';
use Mini\Database as DB;
DB::transaction(function () {
    for ($i=1;$i<=65;$i++) DB::insert('customers',['name'=>'Lookup Customer '.str_pad((string)$i,3,'0',STR_PAD_LEFT),'phone'=>'099'.str_pad((string)$i,6,'0',STR_PAD_LEFT),'address'=>'A long service address in Nay Pyi Taw · မြန်မာ','created_by'=>1]);
});
