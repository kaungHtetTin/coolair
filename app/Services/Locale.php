<?php
declare(strict_types=1);
namespace App\Services;

/** Presentation only. Stored names, statuses, prices and audit snapshots stay intact. */
final class Locale
{
    public static function current(): string {
        $value=$_GET['lang']??$_SERVER['HTTP_X_APP_LOCALE']??$_COOKIE['cool_air_locale']??'en';
        return is_string($value)&&in_array($value,['en','my'],true)?$value:'en';
    }
    public static function catalog(): array {
        static $catalog;
        return $catalog??=array_merge(
            json_decode(file_get_contents(BASE_PATH.'/resources/lang/my.json'),true,512,JSON_THROW_ON_ERROR),
            json_decode(file_get_contents(BASE_PATH.'/resources/lang/messages.my.json'),true,512,JSON_THROW_ON_ERROR)
        );
    }
    public static function translate(string $source,array $params=[]): string {
        if(self::current()==='my') {
            $catalog=self::catalog();
            if(isset($catalog[$source])) $source=$catalog[$source];
            else {
                // Existing validation exceptions keep their stable English messages for callers.
                foreach([
                    '/^Invalid ([a-z_]+)\.$/D'=>['Invalid {field}.',['field']],
                    '/^Check ([a-z_]+): required, maximum (\d+) characters\.$/D'=>['Check {field}: required, maximum {max} characters.',['field','max']],
                    '/^Check ([a-z_]+): maximum (\d+) characters\.$/D'=>['Check {field}: maximum {max} characters.',['field','max']],
                    '/^Enter a valid ([a-z_]+)\.$/D'=>['Enter a valid {field}.',['field']],
                    '/^Enter a valid positive decimal with up to (\d+) decimal places\.$/D'=>['Enter a valid positive decimal with up to {places} decimal places.',['places']]
                ] as $pattern=>[$template,$names]) if(preg_match($pattern,$source,$matches)) {
                    foreach($names as $i=>$name)$params[$name]=$name==='field'?self::translate($matches[$i+1]):$matches[$i+1];
                    $source=$catalog[$template];break;
                }
            }
        }
        $replace=[];foreach($params as $key=>$value)$replace['{'.$key.'}']=(string)$value;
        return strtr($source,$replace);
    }
    public static function error(string $message): array {
        return ['error'=>self::translate($message),'error_source'=>$message];
    }
}
