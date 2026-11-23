<?php

use App\Controllers\HomeController;
use App\Controllers\ApiController;
use App\Controllers\PrintController;
use App\Controllers\FileController;

$router->get('/', [HomeController::class, 'index']);
$router->get('/manifest', [HomeController::class, 'manifest']);
$router->get('/office', [HomeController::class, 'index']);
$router->get('/staff', [HomeController::class, 'index']);
$router->get('/api/{action}', [ApiController::class, 'handle']);
$router->post('/api/{action}', [ApiController::class, 'handle']);
$router->get('/print/document/{id}', [PrintController::class, 'document']);
$router->get('/print/payment/{id}', [PrintController::class, 'receipt']);
$router->post('/upload', [FileController::class, 'upload']);
$router->get('/attachment/{id}', [FileController::class, 'download']);
