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
            'token' => 'required|string',
        ]);

        // Guardamos el token (firstOrCreate evita duplicados directos de ese mismo token para ese usuario)
        $request->user()->fcmTokens()->firstOrCreate([
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

    // Obtener notificaciones del usuario logueado (últimos 30 días), paginadas
    public function getNotifications(Request $request)
    {
        $perPage = 15;

        $paginator = $request->user()->notifications()
            ->recent()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Independiente de la página cargada, para que el badge del navbar sea siempre exacto.
        $unreadCount = $request->user()->notifications()
            ->recent()
            ->where('is_read', false)
            ->count();

        return response()->json([
            'data' => $paginator->items(),
            'current_page' => $paginator->currentPage(),
            'has_more' => $paginator->hasMorePages(),
            'unread_count' => $unreadCount,
        ]);
    }

    // Marcar una sola como leída
    public function markAsRead(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:user_notifications,id',
        ]);

        // El scope de la relación ya filtra por el usuario autenticado: si el id
        // pertenece a otro usuario, no actualiza ninguna fila (evita IDOR).
        $request->user()->notifications()
            ->where('id', $request->id)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Notificación marcada como leída']);
    }

    // Marcar todas como leídas
    public function markAllAsRead(Request $request)
    {
        $request->user()->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Notificaciones marcadas como leídas']);
    }

    // Panel admin: detalle de qué usuario leyó qué notificación (paginado + filtros)
    public function readLog(Request $request)
    {
        $request->validate([
            'email' => 'nullable|string',
            'title' => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $perPage = 20;

        $query = UserNotification::with('user:id,email,nombre')
            ->where('is_read', true);

        if ($request->filled('email')) {
            $email = $request->email;
            $query->whereHas('user', fn ($q) => $q->where('email', 'like', "%{$email}%"));
        }

        if ($request->filled('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        if ($request->filled('date_from')) {
            $query->whereDate('updated_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('updated_at', '<=', $request->date_to);
        }

        $paginator = $query->orderByDesc('updated_at')->paginate($perPage);

        $data = collect($paginator->items())->map(fn ($n) => [
            'id' => $n->id,
            'email' => $n->user->email ?? null,
            'nombre' => $n->user->nombre ?? null,
            'title' => $n->title,
            'body' => $n->body,
            'sent_at' => $n->created_at,
            'read_at' => $n->updated_at,
        ]);

        return response()->json([
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ]);
    }
}
