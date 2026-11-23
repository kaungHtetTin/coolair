<?php

namespace App\Controllers;

use Mini\Controller;

final class HomeController extends Controller
{
    public function manifest(): void
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: no-store');
        $manifest=json_decode(file_get_contents(BASE_PATH.'/public/manifest.webmanifest'),true,512,JSON_THROW_ON_ERROR);
        $manifest['lang']=\App\Services\Locale::current();
        $manifest['description']=__($manifest['description']);
        echo json_encode($manifest,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
    public function index(): void
    {
        header('Cache-Control: no-store');
        view('home', ['title' => 'Cool Air Solution']);
    }

}
