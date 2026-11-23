<?php
use Mini\Database as DB;
DB::transaction(function () {
    $settings = ['company_name'=>'Cool Air Solution Co., Ltd.','company_address'=>'No. (440), Tegyigon, Zabuthiri Township, Nay Pyi Taw','company_phone'=>'09 448548060, 09 689563948','company_email'=>'coolairsolution2021@gmail.com','terms'=>'Estimated quantities and prices may change according to the actual work performed.','currency'=>'MMK'];
    foreach ($settings as $key=>$value) DB::statement('INSERT OR IGNORE INTO settings(key,value) VALUES(?,?)', [$key,$value]);
    foreach (['Cash','Bank transfer','KBZPay','WavePay'] as $name) DB::statement('INSERT OR IGNORE INTO methods(name) VALUES(?)', [$name]);
    foreach (['income'=>['Other income','Service income','Equipment sales'], 'expense'=>['Transportation','Materials purchased','Utilities','Rent','Staff expenses','Other expense']] as $kind=>$names) foreach ($names as $name) DB::statement('INSERT OR IGNORE INTO categories(kind,name) VALUES(?,?)', [$kind,$name]);
});
echo "Company settings and editable categories/methods initialized.\n";
require __DIR__.'/catalog-seed.php';
