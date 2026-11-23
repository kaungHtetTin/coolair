<?php
declare(strict_types=1);
namespace Mini;

final class Production
{
    public static function isSecure(array $server): bool
    {
        if ((!empty($server['HTTPS']) && strtolower((string)$server['HTTPS'])!=='off') || (int)($server['SERVER_PORT']??0)===443) return true;
        $trusted=array_filter(array_map('trim',explode(',',(string)env('TRUSTED_PROXIES',''))));
        return in_array($server['REMOTE_ADDR']??'', $trusted, true)
            && strtolower(trim((string)($server['HTTP_X_FORWARDED_PROTO']??'')))==='https';
    }

    public static function secureCookie(array $server): bool
    {
        $setting=env('SESSION_SECURE_COOKIE','');
        return config('app.environment')==='production' || ($setting===''?self::isSecure($server):filter_var($setting,FILTER_VALIDATE_BOOL));
    }

    public static function redirectTarget(array $server): ?string
    {
        if (!config('app.require_https')) return null;
        $canonical=rtrim((string)env('APP_URL',''),'/');
        $parts=parse_url($canonical);
        if (!$parts || ($parts['scheme']??'')!=='https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \RuntimeException('APP_REQUIRE_HTTPS requires a valid HTTPS APP_URL.');
        }
        $authority=$parts['host'].(isset($parts['port'])?':'.$parts['port']:'');
        if (self::isSecure($server) && strtolower((string)($server['HTTP_HOST']??''))===strtolower($authority)) return null;
        $uri=(string)($server['REQUEST_URI']??'/');
        if (!str_starts_with($uri,'/') || preg_match('/[\r\n]/',$uri)) $uri='/';
        return 'https://'.$authority.'/'.ltrim($uri,'/');
    }

    public static function enforce(): void
    {
        if (PHP_SAPI==='cli') return;
        $target=self::redirectTarget($_SERVER);
        if ($target!==null) {header('Location: '.$target,true,308);exit;}
        header_remove('X-Powered-By');
        if (config('app.environment')==='production' && self::isSecure($_SERVER)) header('Strict-Transport-Security: max-age=31536000');
    }
}
