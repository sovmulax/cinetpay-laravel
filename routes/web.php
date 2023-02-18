<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

/* ========================================== ROUTE CINETPAY ================================================ */
//action route
Route::post('cinetpay/action', [Cinepay::class, 'action'])->name('action');

//return route
Route::post('/cinetpay/{client}', [Cinepay::class, 'return'])->name('return');

//notify route
Route::post('cinetpay/notify', [Cinepay::class, 'notify'])->name('notify');
/* ========================================== FIN ROUTE CINETPAY ================================================ */