<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Validator;
use App\Utils\Auth;
use PDO;

class EmpresaController {

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
        
        if ($user->role === 'MASTER') {
            // Master vê todas as empresas
            $stmt = $this->db->query("SELECT * FROM empresas ORDER BY nome ASC");
            $empresas = $stmt->fetchAll();
        } else {
            // User comum vê as empresas que ele tem acesso
            $stmt = $this->db->prepare("
                SELECT e.*, eu.role as user_role 
                FROM empresas e
                INNER JOIN empresa_users eu ON e.id = eu.empresa_id
                WHERE eu.user_id = ?
                ORDER BY e.nome ASC
            ");
            $stmt->execute([$user->id]);
            $empresas = $stmt->fetchAll();
        }

        $this->jsonResponse(['empresas' => $empresas]);
    }

    public function store() {
        $user = Auth::getUser();
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['nome']) || empty($data['cnpj'])) {
            $this->jsonResponse(['error' => 'Nome e CNPJ são obrigatórios.'], 400);
        }

        $cnpj = preg_replace('/[^0-9]/', '', $data['cnpj']);

        if (!Validator::isCnpjValid($cnpj)) {
            $this->jsonResponse(['error' => 'CNPJ inválido.'], 400);
        }

        // Verifica se CNPJ já existe
        $stmt = $this->db->prepare("SELECT id FROM empresas WHERE cnpj = ?");
        $stmt->execute([$cnpj]);
        if ($stmt->fetch()) {
            $this->jsonResponse(['error' => 'Prefeitura já cadastrada com este CNPJ.'], 409);
        }

        try {
            $this->db->beginTransaction();

            // Insere empresa
            $stmt = $this->db->prepare("INSERT INTO empresas (nome, cnpj) VALUES (?, ?) RETURNING id");
            $stmt->execute([$data['nome'], $cnpj]);
            $empresaId = $stmt->fetchColumn();

            // Vincula o criador como ADMIN (Apenas se não for o MASTER criando solto, mas geralmente o MASTER tbm quer gerenciar)
            $stmt = $this->db->prepare("INSERT INTO empresa_users (user_id, empresa_id, role) VALUES (?, ?, 'ADMIN')");
            $stmt->execute([$user->id, $empresaId]);

            $this->db->commit();

            $this->jsonResponse([
                'message' => 'Prefeitura cadastrada com sucesso.',
                'empresa_id' => $empresaId
            ], 201);

        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->jsonResponse(['error' => 'Erro ao cadastrar prefeitura.'], 500);
        }
    }
}
