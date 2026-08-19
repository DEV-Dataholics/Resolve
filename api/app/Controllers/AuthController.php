<?php

namespace App\Controllers;

use App\Models\UserModel;
use CodeIgniter\API\ResponseTrait;

class AuthController extends BaseController
{
    use ResponseTrait;

    public function login()
    {
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $email    = $this->request->getVar('email');
        $password = $this->request->getVar('password');

        $userModel = new UserModel();
        $user = $userModel->where('email', $email)->first();

        if (!$user || !password_verify($password, $user['password'])) {
            return $this->failUnauthorized('Credenciales inválidas');
        }

        if ($user['status'] !== 'active') {
            return $this->failForbidden('Tu cuenta está desactivada. Contacta al administrador.');
        }

        // Obtener datos de empresa del usuario
        $db      = \Config\Database::connect();
        $company = $db->table('companies')->where('id', $user['company_id'])->get()->getRow();

        session()->set([
            'user_id'    => $user['id'],
            'company_id' => $user['company_id'],
            'role'       => $user['role'],
            'is_internal'=> $company ? (bool) $company->is_internal : false,
            'logged_in'  => true
        ]);

        return $this->respond([
            'status'  => 'success',
            'message' => 'Login exitoso',
            'user'    => [
                'id'          => $user['id'],
                'name'        => $user['name'],
                'role'        => $user['role'],
                'email'       => $user['email'],
                'is_internal' => $company ? (bool) $company->is_internal : false,
                'company_slug'=> $company ? $company->slug : null
            ]
        ]);
    }

    public function logout()
    {
        session()->destroy();
        return $this->respond(['message' => 'Sesión cerrada correctamente']);
    }

    public function me()
    {
        $userId    = session()->get('user_id');
        $userModel = new UserModel();
        $user      = $userModel->find($userId);

        if (!$user) {
            return $this->failUnauthorized('No autorizado');
        }

        $db      = \Config\Database::connect();
        $company = $db->table('companies')->where('id', $user['company_id'])->get()->getRow();

        return $this->respond([
            'id'          => $user['id'],
            'name'        => $user['name'],
            'role'        => $user['role'],
            'email'       => $user['email'],
            'company_id'  => $user['company_id'],
            'is_internal' => $company ? (bool) $company->is_internal : false,
            'company_slug'=> $company ? $company->slug : null,
            'brand_color' => $company ? $company->brand_color : null,
            'logo_url'    => $company ? $company->logo_url : null
        ]);
    }

    // -------------------------------------------------------
    // FORGOT PASSWORD
    // Genera un token y lo envía por email al usuario.
    // -------------------------------------------------------
    public function forgotPassword()
    {
        $email = $this->request->getVar('email');

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Email inválido');
        }

        $db   = \Config\Database::connect();
        $user = $db->table('users')->where('email', $email)->where('status', 'active')->get()->getRow();

        // Por seguridad siempre devolvemos el mismo mensaje,
        // así no revelamos qué emails existen en el sistema.
        $genericMessage = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.';

        if (!$user) {
            return $this->respond(['message' => $genericMessage]);
        }

        // Generar token seguro con expiración de 1 hora
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Eliminar tokens anteriores del mismo email
        $db->table('password_resets')->where('email', $email)->delete();

        // Insertar nuevo token
        $db->table('password_resets')->insert([
            'email'      => $email,
            'token'      => $token,
            'expires_at' => $expiresAt
        ]);

        // Armar el enlace de restablecimiento
        $resetUrl = base_url("reset-password.html?token=$token");

        // Enviar email via CI4 Email Library (usa PHP mail() de cPanel)
        $emailService = \Config\Services::email();
        $emailService->setFrom('no-reply@dataholics.com.mx', 'Dataholics Resolve');
        $emailService->setTo($email);
        $emailService->setSubject('Restablecer contraseña — Dataholics Resolve');
        $emailService->setMessage("
            <p>Hola {$user->name},</p>
            <p>Recibimos una solicitud para restablecer tu contraseña.</p>
            <p><a href='{$resetUrl}' style='background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;'>
               Restablecer contraseña
            </a></p>
            <p>Este enlace expira en <strong>1 hora</strong>.</p>
            <p>Si no solicitaste esto, ignora este mensaje.</p>
            <hr>
            <small>Dataholics Resolve — Sistema de HelpDesk</small>
        ");
        $emailService->setMailType('html');
        $emailService->send();

        return $this->respond(['message' => $genericMessage]);
    }

    // -------------------------------------------------------
    // RESET PASSWORD
    // Valida el token y actualiza la contraseña.
    // -------------------------------------------------------
    public function resetPassword()
    {
        $token       = $this->request->getVar('token');
        $newPassword = $this->request->getVar('password');

        if (!$token || !$newPassword || strlen($newPassword) < 8) {
            return $this->fail('Token y contraseña (mínimo 8 caracteres) son requeridos');
        }

        $db    = \Config\Database::connect();
        $reset = $db->table('password_resets')
            ->where('token', $token)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->get()->getRow();

        if (!$reset) {
            return $this->fail('El enlace es inválido o ha expirado. Solicita uno nuevo.');
        }

        // Actualizar la contraseña del usuario
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $db->table('users')
            ->where('email', $reset->email)
            ->update(['password' => $hashedPassword]);

        // Limpiar el token usado
        $db->table('password_resets')->where('token', $token)->delete();

        return $this->respond(['message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.']);
    }
}
