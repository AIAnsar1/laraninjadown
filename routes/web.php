<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FrontWebHookController;




Route::get('/', function () {
    return view('welcome');
});



Route::post("/frontwebhook", FrontWebHookController::class);























