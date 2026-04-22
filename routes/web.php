<?php

use Illuminate\Support\Facades\Route;
use App\Models\Article;

Route::get('/', function () {
    return view('welcome');
});

// Lien court pour le partage (OpenGraph + Deep link redirection)
Route::get('/s/article/{id}', function ($id) {
    try {
        $article = Article::with('shop')->findOrFail($id);
        return view('share.article', compact('article'));
    } catch (\Exception $e) {
        return redirect('https://colways.sn'); // Fallback site
    }
});
