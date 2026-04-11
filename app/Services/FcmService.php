<?php

namespace App\Services;

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
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ]
                ]
            ];

            if (!empty($data)) {
                $payload['message']['data'] = $data;
            }

            $response = Http::withToken($accessToken)->post($url, $payload);

            if (!$response->successful()) {
                Log::error("FCM Send Error: " . $response->body());
            } else {
                $successCount++;
            }
        }
        
        return $successCount > 0;
    }
}
