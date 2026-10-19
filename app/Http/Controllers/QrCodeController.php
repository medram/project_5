<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QrCodeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('qr-codes/index', [
            'links' => $request->user()->links()
                ->withCount('clicks')
                ->latest()
                ->get(),
        ]);
    }
}
