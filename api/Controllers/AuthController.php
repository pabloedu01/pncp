<?php

namespace App\Controllers;

use App\Config\Database;
use App\Utils\Validator;
use App\Utils\Auth;
use RobThree\Auth\TwoFactorAuth;

use PDO;

class AuthController
{

    private $db;
    private $tfa;

    public function __construct()
    {
        $this->db = Database::getConnection();
        $this->tfa = new TwoFactorAuth('Controle de Obras');
    }

    private function jsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public function register()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['email']) || empty($data['cpf']) || empty($data['password'])) {
            $this->jsonResponse(['error' => 'Dados incompletos.'], 400);
        }

        $cpf = preg_replace('/[^0-9]/', '', $data['cpf']);

        if (!Validator::isCpfValid($cpf)) {
            $this->jsonResponse(['error' => 'CPF inválido.'], 400);
        }

        // Verifica se email ou CPF já existem
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? OR cpf = ?");
        $stmt->execute([$data['email'], $cpf]);
        if ($stmt->fetch()) {
            $this->jsonResponse(['error' => 'Usuário já cadastrado com este E-mail ou CPF.'], 409);
        }

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $secret = $this->tfa->createSecret();

        $role = ($data['email'] === 'opabloedu@gmail.com') ? 'MASTER' : 'USER';

        $stmt = $this->db->prepare("INSERT INTO users (email, cpf, password_hash, two_factor_secret, role) VALUES (?, ?, ?, ?, ?) RETURNING id");
        if ($stmt->execute([$data['email'], $cpf, $hash, $secret, $role])) {
            $userId = $stmt->fetchColumn();

            // Retorna o QR Code para o primeiro setup
            $qrCodeUrl = $this->tfa->getQRCodeImageAsDataUri('Controle Obras (' . $data['email'] . ')', $secret);

            $this->jsonResponse([
                'message' => 'Usuário cadastrado com sucesso. Configure seu 2FA.',
                'qr_code' => $qrCodeUrl,
                'secret' => $secret,
                'user_id' => $userId
            ], 201);
        } else {
            $this->jsonResponse(['error' => 'Falha ao cadastrar.'], 500);
        }
    }

    public function login()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['email']) || empty($data['password'])) {
            $this->jsonResponse(['error' => 'Email e senha são obrigatórios.'], 400);
        }

        $stmt = $this->db->prepare("SELECT id, email, password_hash, role, two_factor_secret FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if ($user && password_verify($data['password'], $user['password_hash'])) {

            if (empty($user['two_factor_secret'])) {
                // Usuário teve o 2FA resetado ou não possui. Vamos gerar um novo.
                $secret = $this->tfa->createSecret();
                $stmt = $this->db->prepare("UPDATE users SET two_factor_secret = ? WHERE id = ?");
                $stmt->execute([$secret, $user['id']]);

                $qrCodeUrl = $this->tfa->getQRCodeImageAsDataUri('Controle Obras (' . $user['email'] . ')', $secret);

                $this->jsonResponse([
                    'message' => 'Você precisa configurar seu Autenticador 2FA novamente.',
                    'setup_2fa' => true,
                    'qr_code' => $qrCodeUrl,
                    'secret' => $secret
                ]);
            } else {
                // Em vez de gerar JWT agora, solicitamos 2FA
                $this->jsonResponse([
                    'message' => 'Credenciais válidas. Informe o código 2FA.',
                    'require_2fa' => true,
                    'temp_token' => base64_encode(json_encode(['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role'], 'time' => time()]))
                ]);
            }
        } else {
            $this->jsonResponse(['error' => 'Credenciais inválidas.'], 401);
        }
    }

    public function verify2fa()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['code']) || empty($data['temp_token'])) {
            $this->jsonResponse(['error' => 'Código 2FA e token temporário são obrigatórios.'], 400);
        }

        $tempData = json_decode(base64_decode($data['temp_token']), true);
        if (!$tempData || time() - $tempData['time'] > 300) { // 5 mins de validade pro token temp
            $this->jsonResponse(['error' => 'Sessão de login expirada.'], 401);
        }

        $stmt = $this->db->prepare("SELECT two_factor_secret FROM users WHERE id = ?");
        $stmt->execute([$tempData['id']]);
        $secret = $stmt->fetchColumn();

        if ($this->tfa->verifyCode($secret, $data['code'])) {
            $jwt = Auth::generateToken([
                'id' => $tempData['id'],
                'email' => $tempData['email'],
                'role' => $tempData['role']
            ]);
            $this->jsonResponse([
                'message' => 'Login realizado com sucesso.',
                'token' => $jwt,
                'role' => $tempData['role']
            ]);
        } else {
            $this->jsonResponse(['error' => 'Código 2FA inválido.'], 401);
        }
    }

    public function forgotPassword()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['email'])) {
            $this->jsonResponse(['error' => 'Email é obrigatório.'], 400);
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            // 1 hora de validade
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $stmt = $this->db->prepare("UPDATE users SET reset_token = ?, reset_expires_at = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $user['id']]);

            // Enviar E-mail via SendGrid
            $emailObj = new \SendGrid\Mail\Mail();
            $emailObj->setFrom("pablo@pabloedu.com", "Controle de Obras");
            $emailObj->setSubject("Recuperacao de Senha - Controle de Obras");
            $emailObj->addTo($data['email']);
            $emailObj->addContent("text/html", "Você solicitou a recuperação de senha.<br>Utilize o token abaixo para redefinir sua senha:<br><br><b>{$token}</b><br><br>Este token expira em 1 hora.");

            $credsFile = __DIR__ . '/../../.credentials.php';
            $creds = file_exists($credsFile) ? require $credsFile : [];
            $sgKey = getenv('SENDGRID_API_KEY') ?: ($creds['SENDGRID_API_KEY'] ?? '');
            $sendgrid = new \SendGrid($sgKey);
            try {
                $response = $sendgrid->send($emailObj);
                if ($response->statusCode() >= 400) {
                    $this->jsonResponse(['error' => 'Erro SendGrid: ' . $response->body()], 500);
                }
            } catch (\Exception $e) {
                $this->jsonResponse(['error' => 'Erro ao enviar e-mail: ' . $e->getMessage()], 500);
            }
        }

        $this->jsonResponse(['message' => 'Se o e-mail existir em nossa base, um token de recuperação foi enviado.']);
    }

    public function resetPassword()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['token']) || empty($data['password'])) {
            $this->jsonResponse(['error' => 'Token e nova senha são obrigatórios.'], 400);
        }

        $stmt = $this->db->prepare("SELECT id, reset_expires_at FROM users WHERE reset_token = ?");
        $stmt->execute([$data['token']]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->jsonResponse(['error' => 'Token inválido.'], 400);
        }

        if (strtotime($user['reset_expires_at']) < time()) {
            $this->jsonResponse(['error' => 'Token expirado.'], 400);
        }

        $hash = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires_at = NULL WHERE id = ?");
        if ($stmt->execute([$hash, $user['id']])) {
            $this->jsonResponse(['message' => 'Senha redefinida com sucesso. Faça login com a nova senha.']);
        } else {
            $this->jsonResponse(['error' => 'Erro ao redefinir a senha.'], 500);
        }
    }

    public function lost2fa()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['temp_token'])) {
            $this->jsonResponse(['error' => 'Sessão inválida.'], 400);
        }

        $tempData = json_decode(base64_decode($data['temp_token']), true);
        if (!$tempData || time() - $tempData['time'] > 300) {
            $this->jsonResponse(['error' => 'Sessão de login expirada. Refaça o login.'], 401);
        }

        $token = sprintf("%06d", mt_rand(1, 999999));
        $expires = date('Y-m-d H:i:s', time() + 900); // 15 mins

        $stmt = $this->db->prepare("UPDATE users SET two_factor_recovery_token = ?, two_factor_recovery_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $tempData['id']]);

        // Enviar E-mail via SendGrid
        $emailObj = new \SendGrid\Mail\Mail();
        $emailObj->setFrom("pablo@pabloedu.com", "Controle de Obras");
        $emailObj->setSubject("Recuperacao de 2FA - Controle de Obras");
        $emailObj->addTo($tempData['email']);
        $emailObj->addContent("text/html", "Você solicitou a recuperação do seu Autenticador 2FA.<br>Utilize o código abaixo para confirmar e configurar um novo autenticador:<br><br><b>{$token}</b><br><br>Este código expira em 15 minutos.");

        $credsFile = __DIR__ . '/../../.credentials.php';
        $creds = file_exists($credsFile) ? require $credsFile : [];
        $sgKey = getenv('SENDGRID_API_KEY') ?: ($creds['SENDGRID_API_KEY'] ?? '');
        $sendgrid = new \SendGrid($sgKey);
        try {
            $response = $sendgrid->send($emailObj);
            if ($response->statusCode() >= 400) {
                $this->jsonResponse(['error' => 'Erro SendGrid: ' . $response->body()], 500);
            }
        } catch (\Exception $e) {
            $this->jsonResponse(['error' => 'Erro ao enviar e-mail: ' . $e->getMessage()], 500);
        }

        $this->jsonResponse(['message' => 'Um código de recuperação foi enviado para o seu e-mail.']);
    }

    public function resetLost2fa()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['temp_token']) || empty($data['code'])) {
            $this->jsonResponse(['error' => 'Dados incompletos.'], 400);
        }

        $tempData = json_decode(base64_decode($data['temp_token']), true);
        if (!$tempData || time() - $tempData['time'] > 300) {
            $this->jsonResponse(['error' => 'Sessão de login expirada. Refaça o login.'], 401);
        }

        $stmt = $this->db->prepare("SELECT id, two_factor_recovery_expires FROM users WHERE id = ? AND two_factor_recovery_token = ?");
        $stmt->execute([$tempData['id'], $data['code']]);
        $user = $stmt->fetch();

        if (!$user) {
            $this->jsonResponse(['error' => 'Código inválido.'], 400);
        }

        if (strtotime($user['two_factor_recovery_expires']) < time()) {
            $this->jsonResponse(['error' => 'Código expirado.'], 400);
        }

        // Gera novo 2FA
        $secret = $this->tfa->createSecret();
        $stmt = $this->db->prepare("UPDATE users SET two_factor_secret = ?, two_factor_recovery_token = NULL, two_factor_recovery_expires = NULL WHERE id = ?");
        $stmt->execute([$secret, $user['id']]);

        $qrCodeUrl = $this->tfa->getQRCodeImageAsDataUri('Controle Obras (' . $tempData['email'] . ')', $secret);

        $this->jsonResponse([
            'message' => 'Código verificado. Configure seu novo Autenticador.',
            'setup_2fa' => true,
            'qr_code' => $qrCodeUrl,
            'secret' => $secret
        ]);
    }
}
