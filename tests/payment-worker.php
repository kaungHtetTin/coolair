<?php
require dirname(__DIR__).'/bootstrap/app.php';
$_SESSION=['user_id'=>(int)$argv[3]];
try {
    \App\Services\Business::collect(['request_key'=>bin2hex(random_bytes(16)),'job_id'=>(int)$argv[1],'document_id'=>(int)$argv[2],'date'=>'2026-11-22','method'=>'Cash','amount'=>600]);
    echo 'saved';
} catch (RuntimeException $e) {
    if ($e->getCode()!==422) throw $e;
    echo 'blocked';
}
