<?php
declare(strict_types=1);
putenv('DB_PATH=:memory:');
require dirname(__DIR__).'/bootstrap/app.php';
use Mini\Database as DB;
foreach (glob(BASE_PATH.'/migrations/*.php') as $file) foreach ((require $file)['up'] as $sql) DB::connection()->exec($sql);
$data=require BASE_PATH.'/database/catalog-data.php';
function verifySeed(bool $condition,string $message): void { if (!$condition) throw new RuntimeException($message); }
verifySeed(count($data['items'])===66,'Expected 66 readable catalog items.');
verifySeed(count($data['rates'])===152,'Expected all 152 equipment-dependent service rates.');
verifySeed(count(array_unique(array_column($data['items'],'key')))===66,'Source keys must be unique.');
foreach ($data['items'] as $item) {
    verifySeed(is_int($item['price']) && $item['price']>=0,'Prices must be nonnegative whole MMK.');
    foreach (explode(';',$item['source']) as $source) {
        $source=trim(explode(' · ',trim($source))[0]);
        if (str_contains($source,'.jpg')) verifySeed(is_file(BASE_PATH.'/_docs/'.$source),'Source image must exist: '.$source);
    }
}
$first=$data['items'][0];
$existing=DB::insert('catalog',['kind'=>$first['kind'],'name'=>$first['name'],'category'=>'Office custom category','specification'=>$first['specification'],'unit'=>$first['unit'],'price'=>29999,'active'=>0]);
require BASE_PATH.'/database/seed.php';
verifySeed((int)DB::fetchValue('SELECT COUNT(*) FROM catalog')===66,'Exact existing item must be adopted, not duplicated.');
verifySeed((int)DB::fetchValue('SELECT COUNT(*) FROM service_rates')===152,'Every service combination must be seeded.');
verifySeed((int)DB::fetchValue('SELECT catalog_id FROM catalog_sources WHERE seed_key=?',['material-01'])===$existing,'Existing item linked to source.');
$preserved=DB::fetch('SELECT * FROM catalog WHERE id=?',[$existing]);
verifySeed($preserved['price']===29999 && $preserved['active']===0 && $preserved['category']==='Office custom category','Existing office values must remain unchanged.');
$installation=(int)DB::fetchValue('SELECT catalog_id FROM catalog_sources WHERE seed_key=?',['service-installation']);
verifySeed(DB::fetchValue("SELECT price FROM service_rates WHERE catalog_id=? AND equipment_type='Split (wall mounted)' AND technology='Inverter' AND hp='1.5'",[$installation])===55000,'Inverter 1.5 HP installation rate.');
$service=(int)DB::fetchValue('SELECT catalog_id FROM catalog_sources WHERE seed_key=?',['service-servicing']);
verifySeed(DB::fetchValue("SELECT price FROM service_rates WHERE catalog_id=? AND equipment_type='Floor ceiling (suspended)' AND technology='Inverter' AND hp='5'",[$service])===87000,'Preserve printed 5 HP inverter floor-ceiling servicing rate.');
verifySeed(!DB::fetchValue("SELECT COUNT(*) FROM catalog_sources WHERE seed_key IN ('material-25','material-26')"),'Unclear material rows must be skipped.');
DB::update('catalog',$existing,['name'=>'Office renamed copper pipe','price'=>31000]);
DB::statement("UPDATE service_rates SET price=123456,equipment_type='Office custom equipment' WHERE id=1");
$counts=DB::fetchPairs("SELECT 'catalog',COUNT(*) FROM catalog UNION ALL SELECT 'rates',COUNT(*) FROM service_rates UNION ALL SELECT 'categories',COUNT(*) FROM categories UNION ALL SELECT 'audit',COUNT(*) FROM audit");
require BASE_PATH.'/database/seed.php';
$after=DB::fetchPairs("SELECT 'catalog',COUNT(*) FROM catalog UNION ALL SELECT 'rates',COUNT(*) FROM service_rates UNION ALL SELECT 'categories',COUNT(*) FROM categories UNION ALL SELECT 'audit',COUNT(*) FROM audit");
verifySeed($counts===$after,'Repeated seed must not duplicate records or audit entries.');
verifySeed(DB::fetchValue('SELECT price FROM catalog WHERE id=?',[$existing])===31000,'Edited price preserved on rerun.');
verifySeed(DB::fetchValue('SELECT price FROM service_rates WHERE id=1')===123456,'Edited service rate preserved on rerun.');
verifySeed(DB::fetchValue('SELECT equipment_type FROM service_rates WHERE id=1')==='Office custom equipment','Edited rate dimensions must not be recreated from the source.');
verifySeed(DB::fetchValue('PRAGMA integrity_check')==='ok','SQLite integrity.');
verifySeed(!DB::fetchValue('SELECT COUNT(*) FROM documents') && !DB::fetchValue('SELECT COUNT(*) FROM payments') && !DB::fetchValue('SELECT COUNT(*) FROM users'),'No customer documents, collections, or accounts seeded.');
echo "Passed catalog seed checks: 66 items, 152 rates, source identities, readable entries, idempotence, and preservation of office edits.\n";
