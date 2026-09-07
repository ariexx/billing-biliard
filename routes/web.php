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

/*
 * Wizard pemasangan. Hanya hidup selama storage/installed belum ada; setelah itu
 * AbortIfInstalled membuatnya 404 karena route ini menulis .env dan membuat akun
 * admin.
 */
Route::middleware(\App\Http\Middleware\AbortIfInstalled::class)
    ->prefix('install')
    ->name('install.')
    ->group(function () {
        Route::get('/', [\App\Http\Controllers\InstallController::class, 'show'])->name('show');
        Route::post('/test-database', [\App\Http\Controllers\InstallController::class, 'testDatabase'])->name('test-database');
        Route::post('/', [\App\Http\Controllers\InstallController::class, 'install'])->name('run');
    });

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
    Route::get('/order-history/export', [\App\Http\Controllers\HomeController::class, 'exportOrderHistory'])
        ->name('order-history.export');
    Route::get('/order-history/drinks', [\App\Http\Controllers\HomeController::class, 'orderHistoryDrinks'])
        ->name('order-history.drinks');
    Route::get('/order-history/drinks/export', [\App\Http\Controllers\HomeController::class, 'exportOrderHistoryDrinks'])
        ->name('order-history.drinks.export');

    Route::get('/order/cari', [\App\Http\Controllers\OrderController::class, 'cari'])
        ->name('order.cari');

    //order view
    Route::get('/order/{uuid}', [\App\Http\Controllers\OrderController::class, 'view'])
        ->name('order.view');
    // order.edit dihapus: view-nya memanggil route('order.update') yang tidak
    // pernah didaftarkan (RouteNotFoundException / 500) dan templatenya terpotong
    // tanpa @endforeach maupun tombol submit. Penyuntingan item order sudah
    // ditangani order-item.edit.

    Route::post('/order/{uuid}/bayar', [\App\Http\Controllers\OrderController::class, 'bayar'])
        ->name('order.bayar');

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
