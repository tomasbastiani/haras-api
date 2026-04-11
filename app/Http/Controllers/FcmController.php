<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserFcmToken;
use App\Models\UserNotification;
use App\Services\FcmService;

class FcmController extends Controller
{
    public function saveToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        // Guardamos el token (firstOrCreate evita duplicados directos de ese mismo token para ese usuario)
        $user->fcmTokens()->firstOrCreate([
            'token' => $request->token,
        ]);

        return response()->json(['message' => 'Token saved successfully']);
    }

    // Enviar notificación de prueba a un email específico
    public function sendTest(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();
        $tokens = $user->fcmTokens()->pluck('token')->toArray();

        if (empty($tokens)) {
            return response()->json(['message' => 'Este usuario no tiene tokens FCM registrados'], 404);
        }

        $result = FcmService::send(
            $tokens,
            '🔔 Prueba de Notificación',
            '¡Las notificaciones de Haras Santa Maria están funcionando correctamente!'
        );

        return response()->json([
            'message' => $result ? 'Notificación enviada con éxito' : 'Error al enviar',
            'tokens_count' => count($tokens),
        ]);
    }

    // Enviar notificación personalizada desde el admin
    public function sendNotification(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:500',
            'target' => 'required|in:all,specific',
            'emails' => 'required_if:target,specific|array',
        ]);

        if ($request->target === 'all') {
            $users = User::all();
            $tokens = UserFcmToken::pluck('token')->toArray();
            
            // Guardar historial para TODOS
            $historyData = $users->map(fn($u) => [
                'user_id' => $u->id,
                'title' => $request->title,
                'body' => $request->body,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();
            UserNotification::insert($historyData);
        } else {
            $userIds = User::whereIn('email', $request->emails)->pluck('id');
            $tokens = UserFcmToken::whereIn('user_id', $userIds)->pluck('token')->toArray();
            
            // Guardar historial para específicos
            $historyData = $userIds->map(fn($id) => [
                'user_id' => $id,
                'title' => $request->title,
                'body' => $request->body,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ])->toArray();
            UserNotification::insert($historyData);
        }

        if (empty($tokens)) {
            return response()->json(['message' => 'No se encontraron dispositivos conectados, pero se guardó en el historial'], 200);
        }

        $result = FcmService::send($tokens, $request->title, $request->body);

        return response()->json([
            'message' => $result ? 'Notificaciones enviadas y guardadas' : 'Error al enviar push',
            'devices_reached' => count($tokens),
        ]);
    }

    // Obtener notificaciones del usuario logueado (últimos 30 días)
    public function getNotifications(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);
        
        $user = User::where('email', $request->email)->first();
        
        $notifications = $user->notifications()
            ->recent()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    // Marcar una sola como leída
    public function markAsRead(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:user_notifications,id',
            'email' => 'required|email|exists:users,email'
        ]);
        
        $user = User::where('email', $request->email)->first();
        
        $user->notifications()
            ->where('id', $request->id)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Notificación marcada como leída']);
    }

    // Marcar todas como leídas
    public function markAllAsRead(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);
        
        $user = User::where('email', $request->email)->first();
        
        $user->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Notificaciones marcadas como leídas']);
    }
}
