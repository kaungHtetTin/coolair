<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap/app.php';
use App\Services\Locale;
use App\Controllers\PrintController;

function checkLocale(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$catalog=Locale::catalog();
foreach($catalog as $source=>$translated){
    checkLocale(is_string($translated)&&trim($translated)!=='','Empty translation: '.$source);
    preg_match_all('/\{\w+\}/u',$source,$sourceParams);preg_match_all('/\{\w+\}/u',$translated,$targetParams);
    sort($sourceParams[0]);sort($targetParams[0]);checkLocale($sourceParams[0]===$targetParams[0],'Translation placeholders differ: '.$source);
}
// Static domain errors must always have Myanmar translations. Interpolated validators are checked below.
foreach(array_merge(glob(BASE_PATH.'/app/Controllers/*.php'),glob(BASE_PATH.'/app/Services/*.php')) as $file){
    preg_match_all("/::fail\('([^']+)'\s*[,)]/",file_get_contents($file),$matches);
    foreach($matches[1] as $message)checkLocale(isset($catalog[$message]),'Missing validation translation: '.$message);
}
$_GET=[];$_COOKIE=[];$_SERVER['HTTP_X_APP_LOCALE']='en';
checkLocale(__('Customer')==='Customer','English is unchanged');
checkLocale(PrintController::words(740700)==='Seven Hundred Forty Thousand Seven Hundred','English words unchanged');
$_SERVER['HTTP_X_APP_LOCALE']='my';
checkLocale(__('Customer')==='ဖောက်သည်','Myanmar selected by header');
checkLocale(__('Check name: required, maximum 150 characters.')==='အမည် ကို ဖြည့်ပါ။ အများဆုံး စာလုံး 150 လုံး ဖြစ်ရမည်။','Parameterized validation');
checkLocale(__('Enter a valid due_date.')==='မှန်ကန်သော ပေးချေရမည့်ရက် ထည့်ပါ။','Date validation');
checkLocale(__('Enter a valid positive decimal with up to 3 decimal places.')==='ဒသမ 3 နေရာအထိရှိသော မှန်ကန်သည့် အပေါင်းကိန်း ထည့်ပါ။','Decimal validation');
checkLocale(PrintController::words(0)==='သုည','Zero words');
checkLocale(PrintController::words(21)==='နှစ်ဆယ့်တစ်','Tens words');
checkLocale(PrintController::words(740700)==='ခုနစ်သိန်းလေးသောင်းခုနစ်ရာ','Myanmar currency words');
checkLocale(PrintController::words(100000000000)==='တစ်ရာဘီလီယံ','Maximum amount words');
checkLocale(__('Page {page} of {pages}',['page'=>'{PAGENO}','pages'=>'{nbpg}'])==='စာမျက်နှာ {PAGENO} / {nbpg}','mPDF placeholders preserved');
checkLocale(Locale::error('Access denied.')===['error'=>'ဝင်ရောက်ခွင့် မရှိပါ။','error_source'=>'Access denied.'],'Localized errors preserve source key');
$_GET['lang']='invalid<script>';checkLocale(Locale::current()==='en','Invalid locale safely falls back');
$_GET['lang']='en';checkLocale(__('Customer')==='Customer','Print query overrides header');
unset($_GET['lang'],$_SERVER['HTTP_X_APP_LOCALE']);$_COOKIE['cool_air_locale']='my';checkLocale(Locale::current()==='my','Print cookie preference');
echo 'Passed locale checks: '.count($catalog)." translations, placeholder parity, validation coverage, locale selection and Myanmar currency words.\n";
