<?php

use App\Http\Controllers\DobbleProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DobbleProjectController::class, 'index'])->name('dobble.index');
Route::post('/configuration', [DobbleProjectController::class, 'updateConfiguration'])->name('dobble.configuration');
Route::get('/card-background', [DobbleProjectController::class, 'showCardBackground'])->name('dobble.background');
Route::post('/symbols/{symbol}', [DobbleProjectController::class, 'uploadSymbol'])->whereNumber('symbol')->name('dobble.symbols.upload');
Route::delete('/symbols/{symbol}', [DobbleProjectController::class, 'removeSymbol'])->whereNumber('symbol')->name('dobble.symbols.remove');
Route::get('/symbols/{symbol}', [DobbleProjectController::class, 'showSymbol'])->whereNumber('symbol')->name('dobble.symbols.show');
Route::get('/print', [DobbleProjectController::class, 'print'])->name('dobble.print');
Route::post('/reset', [DobbleProjectController::class, 'reset'])->name('dobble.reset');
