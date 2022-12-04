<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Auth::routes();

Route::group(['middleware' => 'auth'], function () {
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])
        ->name('home');
    Route::get('/order-history', [\App\Http\Controllers\HomeController::class, 'orderHistory'])
        ->name('order-history');

    //order view
    Route::get('/order/{uuid}', [\App\Http\Controllers\OrderController::class, 'view'])
        ->name('order.view');
    Route::get('/order/{uuid}/edit', [\App\Http\Controllers\OrderController::class, 'edit'])
        ->name('order.edit');
//    Route::put('/order/{uuid}/update', [\App\Http\Controllers\OrderController::class, 'update'])
//        ->name('order.update');

    //order item
    Route::get('/order-item/{uuidOrder}/edit', [\App\Http\Controllers\OrderItemController::class, 'edit'])
        ->name('order-item.edit');
    Route::delete('/order-item/{uuid}/delete', [\App\Http\Controllers\OrderItemController::class, 'destroy'])
        ->name('order-item.destroy');
});
