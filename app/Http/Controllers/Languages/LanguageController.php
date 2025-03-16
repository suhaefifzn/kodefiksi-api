<?php

namespace App\Http\Controllers\Languages;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Models
use App\Models\Language;

class LanguageController extends Controller
{
    public function getAll() {
        $languages = Language::all();
        return parent::successfulResponseJSON(null, [
            'languages' => $languages
        ]);
    }
}
