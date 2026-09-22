Route::delete('destinations', 'ItemController@destroy')->name('destinations.destroy');
Route::delete('destinations/all', 'ItemController@destroyAll')->name('destinations.destroy-all');

Route::delete('tour-packages', 'ItemController@destroy')->name('tour-packages.destroy');
Route::delete('tour-packages/all', 'ItemController@destroyAll')->name('tour-packages.destroy-all');
