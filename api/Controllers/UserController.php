<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Auth;
use PDO;

class UserController {

    private $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function index() {
        $user = Auth::getUser();
        
        if ($user->role !== 'MASTER') {
            $this->jsonResponse(['error' => 'Acesso negado. Apenas MASTER.'], 403);
        }

        $stmt = $this->db->query("SELECT id, email, cpf, role, (two_factor_secret IS NOT NULL) as has_2fa, created_at FROM users ORDER BY id DESC");
        $users = $stmt->fetchAll();

        $this->jsonResponse(['users' => $users]);
    }

    public function reset2fa() {
        $user = Auth::getUser();
        
        if ($user->role !== 'MASTER') {
            $this->jsonResponse(['error' => 'Acesso negado. Apenas MASTER.'], 403);
        }

        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['user_id'])) {
            $this->jsonResponse(['error' => 'ID do usuário é obrigatório.'], 400);
        }

        $stmt = $this->db->prepare("UPDATE users SET two_factor_secret = NULL WHERE id = ?");
        if ($stmt->execute([$data['user_id']])) {
            $this->jsonResponse(['message' => 'Autenticador 2FA removido com sucesso. O usuário precisará configurar novamente no próximo login.']);
        } else {
            $this->jsonResponse(['error' => 'Erro ao resetar 2FA.'], 500);
        }
    }
}
