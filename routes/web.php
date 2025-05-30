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
    if (auth()->check()) {
        return redirect()->route('home');
    } else {
        return view('auth.login');
    }
});
Auth::routes(['register' => false, 'reset' => false, 'verify' => false]);

Route::group(['middleware' => 'auth'], function () {
    Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])
        ->name('home');
    Route::get('/order-history', [\App\Http\Controllers\HomeController::class, 'orderHistory'])
        ->name('order-history');
    Route::get('/order-history/drinks', [\App\Http\Controllers\HomeController::class, 'orderHistoryDrinks'])
        ->name('order-history.drinks');

    //order view
    Route::get('/order/{uuid}', [\App\Http\Controllers\OrderController::class, 'view'])
        ->name('order.view');
    Route::get('/order/{uuid}/edit', [\App\Http\Controllers\OrderController::class, 'edit'])
        ->name('order.edit');
    //    Route::put('/order/{uuid}/update', [\App\Http\Controllers\OrderController::class, 'update'])
    //        ->name('order.update');
    Route::get('/order/{uuid}/pindah-meja', [\App\Http\Controllers\OrderController::class, 'pindahMeja'])
        ->name('order.pindah-meja');

    Route::put('/order/{uuid}/pindah-meja', [\App\Http\Controllers\OrderController::class, 'pindahMejaPost'])
        ->name('order.pindah-meja');

    //order item
    Route::put('/order-item/{orderUuid}/update', [\App\Http\Controllers\OrderItemController::class, 'update'])
        ->name('order-item.update');
    Route::get('/order-item/{uuidOrder}/edit', [\App\Http\Controllers\OrderItemController::class, 'edit'])
        ->name('order-item.edit');
    Route::delete('/order-item/{uuid}/delete', [\App\Http\Controllers\OrderItemController::class, 'destroy'])
        ->name('order-item.destroy');

    //print
    Route::post('/print', \App\Http\Controllers\PrintController::class)->name('print');

    //only admin can access this route
    Route::get('logs', [\Rap2hpoutre\LaravelLogViewer\LogViewerController::class, 'index'])
        ->middleware('can:isAdmin')
        ->name('logs');
});
