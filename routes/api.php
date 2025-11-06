<?php

use App\Http\Controllers\TransferController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/deposit', [TransferController::class, 'deposit']);
Route::post('/withdraw', [TransferController::class, 'withdraw']);
Route::post('/transfer', [TransferController::class, 'transfer']);
Route::get('/balance/{user}', [TransferController::class, 'balance'])->missing(function (Request $request) {
    return response()->json(['status' => 'User wasnt found'], 404);
});
