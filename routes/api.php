<?php

use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\vsrcreationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
/*
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
*/
Route::middleware('auth:sanctum')->post("insert/vsr",[vsrcreationController::class,"index"]);
//Route::post("insert/vsr", [vsrcreationController::class,"index"]);
Route::post("user/createToken", [UserController::class,"createToken"]);
Route::post("create-vsrnum", [vsrcreationController::class,"vsrInterfaceWithChild"]);
