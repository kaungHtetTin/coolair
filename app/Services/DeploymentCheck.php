<?php
declare(strict_types=1);
namespace App\Services;

final class DeploymentCheck
{
    public static function prepare(): void
    {
        foreach (['database','logs','uploads','pdf','cache','backups'] as $name) {
            $path=BASE_PATH.'/storage/'.$name;
            if (!is_dir($path) && !mkdir($path,0770,true) && !is_dir($path)) throw new \RuntimeException('Cannot create storage/'.$name);
        }
    }

    /** Readiness checks do not create a database or alter existing business data. */
    public static function checks(): array
    {
        $checks=[];
        $add=static function(bool $ok,string $label) use (&$checks): void {$checks[]=[$ok,$label];};
        $add(config('app.environment')==='production','APP_ENV=production');
        $add(!filter_var(env('APP_DEBUG',false),FILTER_VALIDATE_BOOL),'APP_DEBUG=false');
        $parts=parse_url((string)env('APP_URL',''));
        $add(is_array($parts) && ($parts['scheme']??'')==='https' && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment']),'APP_URL is an HTTPS canonical URL');
        $add((bool)config('app.require_https'),'HTTPS enforcement enabled');
        $add(!config('app.allow_setup'),'Public administrator setup disabled');
        $add(filter_var(env('SESSION_SECURE_COOKIE',false),FILTER_VALIDATE_BOOL),'SESSION_SECURE_COOKIE=true');
        $add(PHP_VERSION_ID>=80200,'PHP 8.2 or newer');
        foreach (['pdo_sqlite','bcmath','fileinfo','mbstring','gd','dom','session'] as $extension) $add(extension_loaded($extension),'PHP extension: '.$extension);
        $add(class_exists(\Mpdf\Mpdf::class),'Composer PDF dependencies installed');
        foreach (['database','logs','uploads','pdf','cache','backups'] as $name) $add(is_dir(BASE_PATH.'/storage/'.$name) && is_writable(BASE_PATH.'/storage/'.$name),'Writable persistent storage/'.$name);
        $manifestFile=BASE_PATH.'/public/build/.vite/manifest.json';
        $manifest=is_file($manifestFile)?json_decode(file_get_contents($manifestFile),true):[];
        $entry=$manifest['resources/js/app.jsx']??[];
        $assets=isset($entry['file']) && is_file(BASE_PATH.'/public/build/'.$entry['file']);
        foreach($entry['css']??[] as $file)$assets=$assets && is_file(BASE_PATH.'/public/build/'.$file);
        $add($assets,'Frontend production build present');
        foreach (['public/branding/logo.jpg','public/branding/letterhead.jpg','public/icons/logo-192.png','public/icons/logo-512.png','resources/fonts/Tharlon-Regular.ttf','resources/lang/my.json','resources/lang/messages.my.json'] as $file) $add(is_file(BASE_PATH.'/'.$file),'Bundled asset: '.$file);
        $path=(string)env('DB_PATH','storage/database/app.sqlite');
        if (!preg_match('#^(?:[A-Za-z]:[\\\\/]|/)#',$path)) $path=BASE_PATH.'/'.$path;
        $resolved=realpath($path);
        $public=realpath(BASE_PATH.'/public');
        $private=$resolved!==false && $public!==false && !str_starts_with(str_replace('\\','/',$resolved),str_replace('\\','/',$public).'/');
        $add($private && is_file($path) && is_writable($path) && is_writable(dirname($path)),'Persistent writable SQLite database outside public/');
        if ($resolved!==false && is_file($path)) {
            try {
                $pdo=new \PDO('sqlite:'.$resolved,null,null,[\PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION]);
                $applied=$pdo->query('SELECT migration FROM migrations')->fetchAll(\PDO::FETCH_COLUMN);
                $pending=array_diff(array_map('basename',glob(BASE_PATH.'/migrations/*.php')),$applied);
                $add(!$pending,'All database migrations applied');
                $add((int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='office' AND active=1")->fetchColumn()>0,'An active office administrator exists');
                $add((int)$pdo->query('SELECT COUNT(*) FROM catalog')->fetchColumn()>0 && (int)$pdo->query('SELECT COUNT(*) FROM service_rates')->fetchColumn()>0,'Catalog and service pricing initialized');
            } catch (\Throwable) {$add(false,'Database schema ready (run migrations before checking)');}
        }
        return $checks;
    }
}
