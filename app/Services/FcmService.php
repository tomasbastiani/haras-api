<?php

namespace App\Services;

use App\Models\UserFcmToken;
use Google\Auth\CredentialsLoader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    /**
     * Enviar notificacion push usando Firebase v1 API
     * 
     * @param array|string $tokens
     * @param string $title
     * @param string $body
     * @param array $data Additional invisible data
     */
    public static function send($tokens, $title, $body, $data = [])
    {
        // Ruta al archivo descargado desde Firebase Console
        $credentialsPath = env('FIREBASE_CREDENTIALS', storage_path('app/firebase_credentials.json'));

        if (!file_exists($credentialsPath)) {
            Log::error("FCM: Credentials file not found at {$credentialsPath}");
            return false;
        }

        try {
            $jsonKey = json_decode(file_get_contents($credentialsPath), true);
            $projectId = $jsonKey['project_id'];

            $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
            $credentials = CredentialsLoader::makeCredentials($scopes, $jsonKey);
            $tokenArray = $credentials->fetchAuthToken();
            $accessToken = $tokenArray['access_token'];
        } catch (\Exception $e) {
            Log::error("FCM: Error getting access token - " . $e->getMessage());
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        if (!is_array($tokens)) {
            $tokens = [$tokens];
        }

        $successCount = 0;
        foreach ($tokens as $deviceToken) {
            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'data' => [
                        'title' => (string)$title,
                        'body' => (string)$body,
                        'url' => (string)($data['url'] ?? 'https://harassantamaria.com.ar/login'),
                    ]
                ]
            ];

            // Si hay mas datos personalizados, los mezclamos
            if (!empty($data) && isset($data['url'])) {
                unset($data['url']); // Ya la pusimos arriba
            }
            if (!empty($data)) {
                $payload['message']['data'] = array_merge($payload['message']['data'], $data);
            }

            $response = Http::withToken($accessToken)->post($url, $payload);

            if (!$response->successful()) {
                Log::error("FCM Send Error: " . $response->body());

                // Si el dispositivo ya no está registrado o el token no existe
                // Ver error: https://firebase.google.com/docs/reference/fcm/rest/v1/ErrorCode
                if ($response->status() === 404) {
                    Log::info("FCM: Eliminando token inválido/unregistered de la BD: " . $deviceToken);
                    UserFcmToken::where('token', $deviceToken)->delete();
                }
            } else {
                $successCount++;
            }
        }
        
        return $successCount > 0;
    }
}
