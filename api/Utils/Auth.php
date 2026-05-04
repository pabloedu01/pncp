<?php

namespace App\Utils;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class Auth {
    private static $user = null;

    private static function getSecret() {
        $credsFile = __DIR__ . '/../../.credentials.php';
        if (file_exists($credsFile)) {
            $creds = require $credsFile;
            return $creds['JWT_SECRET'] ?? 'SEGREDO_SUPER_FORTE_MUDAR_NA_PRODUCAO';
        }
        return 'SEGREDO_SUPER_FORTE_MUDAR_NA_PRODUCAO';
    }

    public static function generateToken($user) {
        $payload = [
            'iss' => 'pncp',
            'aud' => 'pncp_app',
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24), // 24 horas
            'data' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];
        return JWT::encode($payload, self::getSecret(), 'HS256');
    }

    public static function checkToken() {
        $authHeader = null;
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = trim($_SERVER['HTTP_AUTHORIZATION']);
        } elseif (isset($_SERVER['Authorization'])) {
            $authHeader = trim($_SERVER['Authorization']);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            if (isset($requestHeaders['Authorization'])) {
                $authHeader = trim($requestHeaders['Authorization']);
            }
        }

        if (!$authHeader) {
            http_response_code(401);
            echo json_encode(['error' => 'Acesso negado. Token não fornecido.']);
            exit;
        }

        $arr = explode(" ", $authHeader);
        $jwt = $arr[1] ?? '';

        if (!$jwt) {
            http_response_code(401);
            echo json_encode(['error' => 'Acesso negado. Formato de token inválido.']);
            exit;
        }

        try {
            $decoded = JWT::decode($jwt, new Key(self::getSecret(), 'HS256'));
            self::$user = $decoded->data;
            return $decoded->data;
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido ou expirado.']);
            exit;
        }
    }

    public static function getUser() {
        return self::$user;
    }
}
