<?php
declare(strict_types=1);
putenv('APP_ENV=production');putenv('APP_DEBUG=true');putenv('APP_URL=https://coolair.k2studioapp.com');
putenv('APP_REQUIRE_HTTPS=true');putenv('APP_ALLOW_SETUP=false');putenv('SESSION_SECURE_COOKIE=true');putenv('TRUSTED_PROXIES=');
require dirname(__DIR__).'/bootstrap/app.php';
use Mini\Production;
function productionCheck(bool $value,string $message): void {if(!$value)throw new RuntimeException($message);}
productionCheck(!config('app.debug'),'Production never exposes debug errors');
productionCheck(!config('app.allow_setup'),'Production setup disabled');
$request=['HTTP_HOST'=>'coolair.k2studioapp.com','REQUEST_URI'=>'/api/session?lang=my','REMOTE_ADDR'=>'203.0.113.20','HTTPS'=>'off'];
productionCheck(Production::redirectTarget($request)==='https://coolair.k2studioapp.com/api/session?lang=my','HTTPS redirect preserves path/query');
productionCheck(Production::secureCookie($request),'Production cookie always secure');
$request['HTTP_X_FORWARDED_PROTO']='https';
productionCheck(!Production::isSecure($request),'Untrusted forwarded header ignored');
putenv('TRUSTED_PROXIES=203.0.113.10, 203.0.113.20');
productionCheck(Production::isSecure($request),'Explicit proxy accepted');
productionCheck(Production::redirectTarget($request)===null,'No HTTPS loop for trusted proxy');
$request['HTTP_HOST']='untrusted.example';
productionCheck(Production::redirectTarget($request)==='https://coolair.k2studioapp.com/api/session?lang=my','Canonical host cannot be injected');
$request['REQUEST_URI']="/\r\nInjected: value";
productionCheck(Production::redirectTarget($request)==='https://coolair.k2studioapp.com/','Redirect rejects header newlines');
putenv('TRUSTED_PROXIES=');$request['HTTPS']='on';
productionCheck(Production::isSecure($request),'Direct HTTPS detected');
putenv('APP_ENV=testing');putenv('APP_REQUIRE_HTTPS=false');putenv('SESSION_SECURE_COOKIE=');
Mini\Config::load(BASE_PATH.'/config');
productionCheck(Production::redirectTarget($request)===null,'Local routing works without production redirects');
productionCheck(!Production::secureCookie(['HTTPS'=>'off','SERVER_PORT'=>8092]),'HTTP local development cookies work');
echo "Passed production policies: HTTPS, canonical URLs, secure cookies, proxy trust, setup lock and error privacy.\n";
