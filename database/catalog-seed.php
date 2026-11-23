<?php
declare(strict_types=1);
use Mini\Database as DB;

$sourceData=require __DIR__.'/catalog-data.php';
$catalogSeedResult=DB::transaction(function () use ($sourceData): array {
    $ids=[];$created=0;$matched=0;$ratesCreated=0;$categoriesCreated=0;
    foreach ($sourceData['items'] as $item) {
        $id=DB::fetchValue('SELECT catalog_id FROM catalog_sources WHERE seed_key=?',[$item['key']]);
        if (!$id) {
            // Adopt an exact existing identity; never overwrite its price or availability.
            $id=DB::fetchValue('SELECT id FROM catalog WHERE kind=? AND name=? AND specification=? AND unit=? ORDER BY id LIMIT 1',[$item['kind'],$item['name'],$item['specification'],$item['unit']]);
            if ($id) $matched++;
            else {
                $id=DB::insert('catalog',['kind'=>$item['kind'],'name'=>$item['name'],'category'=>$item['category'],'specification'=>$item['specification'],'unit'=>$item['unit'],'price'=>$item['price'],'active'=>1]);
                $created++;
            }
            DB::insert('catalog_sources',['seed_key'=>$item['key'],'catalog_id'=>(int)$id,'source'=>$item['source'],'note'=>$item['note']??'']);
        }
        $ids[$item['key']]=(int)$id;
    }
    foreach ($sourceData['rates'] as $rate) {
        $key=implode('|',[$rate['service_key'],$rate['technology'],$rate['equipment_type'],$rate['hp']]);
        if (DB::fetchValue('SELECT rate_id FROM service_rate_sources WHERE seed_key=?',[$key])) continue;
        $id=DB::fetchValue('SELECT id FROM service_rates WHERE catalog_id=? AND equipment_type=? AND technology=? AND hp=?',[$ids[$rate['service_key']],$rate['equipment_type'],$rate['technology'],$rate['hp']]);
        if (!$id) {
            $id=DB::insert('service_rates',['catalog_id'=>$ids[$rate['service_key']],'equipment_type'=>$rate['equipment_type'],'technology'=>$rate['technology'],'hp'=>$rate['hp'],'price'=>$rate['price']]);
            $ratesCreated++;
        }
        DB::insert('service_rate_sources',['seed_key'=>$key,'rate_id'=>(int)$id,'source'=>$rate['technology']==='Inverter'?'photo_2026-11-22_15-47-03.jpg':'photo_2026-11-22_15-46-53.jpg']);
    }
    foreach ($sourceData['income_categories'] as $category) {
        $stmt=DB::statement("INSERT OR IGNORE INTO categories(kind,name) VALUES('income',?)",[$category]);
        $categoriesCreated+=$stmt->rowCount();
    }
    $result=['catalog_created'=>$created,'existing_matched'=>$matched,'rates_created'=>$ratesCreated,'income_categories_created'=>$categoriesCreated];
    if ($created || $matched || $ratesCreated || $categoriesCreated) DB::insert('audit',['user_id'=>null,'actor_username'=>'System','actor_name'=>'System','action'=>'seeded','entity'=>'catalog','entity_id'=>null,'detail'=>json_encode($result+['source'=>'_docs','note'=>'Existing catalog values and rates preserved.'])]);
    return $result;
});
echo 'Document catalog seed: '.json_encode($catalogSeedResult)."\n";
