<?php

use App\Http\Controllers\TransferController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

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


     
Route::prefix('api')->group(function(){
    Route::post('/deposit',[TransferController::class,'deposit']);
    Route::post('/withdraw',[TransferController::class,'withdraw']);
    Route::post('/transfer',[TransferController::class,'transfer']);
    Route::get('/balance/{user}',[TransferController::class,'balance'])->missing(function(Request $request){
        return response()->json(['status' => 'User wasnt found'],404);
    });
});
    