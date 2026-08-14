<?php
Route::get('/admin/languages/settings', [Pterodactyl\Http\Controllers\Admin\Extensions\translations\translationsExtensionController::class, 'getLanguageSettings'])->name('blueprint.extensions.translations.languages.settings');
Route::post('/admin/languages/settings', [Pterodactyl\Http\Controllers\Admin\Extensions\translations\translationsExtensionController::class, 'saveLanguageSettings'])->name('blueprint.extensions.translations.languages.settings.save');
?>