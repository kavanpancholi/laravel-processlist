<?php

Route::group(['prefix' => 'processlist'], function () {
    Route::get('artisan_processes', 'Kavanpancholi\Processlist\ProcesslistController@getArtisanProcesses');
});