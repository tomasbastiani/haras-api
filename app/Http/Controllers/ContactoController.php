<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactoController extends Controller
{
    public function enviar(Request $request)
    {
        $validated = $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        try {
            Mail::raw($validated['message'], function ($message) use ($validated) {
                $message->to($validated['to'])
                        ->subject($validated['subject']);
            });

            return response()->json(['success' => true, 'message' => 'Email enviado correctamente']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
