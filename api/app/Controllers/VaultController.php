<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class VaultController extends BaseController
{
    use ResponseTrait;

    private function getMasterKey()
    {
        // For security, read from environment variable or `.env` configuration.
        // Fallback for development if not present. In production it MUST be configured.
        $key = getenv('VAULT_MASTER_KEY') ?: env('VAULT_MASTER_KEY');
        if (!$key) {
            $key = '0123456789abcdef0123456789abcdef'; // 32-byte fallback key
        }
        
        // Ensure the key is exactly 32 bytes for aes-256
        if (strlen($key) > 32) {
            $key = substr($key, 0, 32);
        } else if (strlen($key) < 32) {
            $key = str_pad($key, 32, '0');
        }
        
        return $key;
    }

    private function encrypt($plaintext)
    {
        $key = $this->getMasterKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-gcm'));
        $tag = '';
        
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        
        return [
            'payload' => base64_encode($ciphertext),
            'iv'      => base64_encode($iv),
            'tag'     => base64_encode($tag)
        ];
    }

    private function decrypt($payload, $iv, $tag)
    {
        $key = $this->getMasterKey();
        
        return openssl_decrypt(
            base64_decode($payload), 
            'aes-256-gcm', 
            $key, 
            OPENSSL_RAW_DATA, 
            base64_decode($iv), 
            base64_decode($tag)
        );
    }

    public function index()
    {
        if (session()->get('role') !== 'admin') {
            return $this->failForbidden('Acceso denegado');
        }

        $db = \Config\Database::connect();
        $builder = $db->table('project_credentials pc')
            ->select('pc.id, pc.company_id, pc.service_name, pc.environment, pc.username, pc.notes, pc.created_at, pc.updated_at, c.name as company_name')
            ->join('companies c', 'c.id = pc.company_id', 'left')
            ->orderBy('pc.company_id', 'ASC')
            ->orderBy('pc.service_name', 'ASC');

        return $this->respond($builder->get()->getResult());
    }

    public function reveal($id = null)
    {
        if (session()->get('role') !== 'admin') {
            return $this->failForbidden('Acceso denegado');
        }

        $db = \Config\Database::connect();
        $cred = $db->table('project_credentials')->where('id', $id)->get()->getRow();

        if (!$cred) {
            return $this->failNotFound('Credencial no encontrada');
        }

        $plaintext = $this->decrypt($cred->encrypted_payload, $cred->iv, $cred->auth_tag);
        
        if ($plaintext === false) {
            return $this->fail('Error al desencriptar la credencial');
        }

        // Podríamos loguear esto en una tabla audit_logs si la hay
        // log_message('info', 'El admin ' . session()->get('user_id') . ' reveló la credencial ' . $id);

        return $this->respond(['secret' => $plaintext]);
    }

    public function create()
    {
        if (session()->get('role') !== 'admin') {
            return $this->failForbidden('Acceso denegado');
        }

        $rules = [
            'company_id'   => 'required|numeric',
            'service_name' => 'required',
            'environment'  => 'required',
            'secret'       => 'required'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $secret = $this->request->getVar('secret');
        $encData = $this->encrypt($secret);

        $data = [
            'company_id'        => $this->request->getVar('company_id'),
            'service_name'      => $this->request->getVar('service_name'),
            'environment'       => $this->request->getVar('environment'),
            'username'          => $this->request->getVar('username'),
            'encrypted_payload' => $encData['payload'],
            'iv'                => $encData['iv'],
            'auth_tag'          => $encData['tag'],
            'notes'             => $this->request->getVar('notes'),
            'created_by'        => session()->get('user_id'),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s')
        ];

        $db = \Config\Database::connect();
        $db->table('project_credentials')->insert($data);

        return $this->respondCreated(['message' => 'Credencial guardada de forma segura', 'id' => $db->insertID()]);
    }

    public function update($id = null)
    {
        if (session()->get('role') !== 'admin') {
            return $this->failForbidden('Acceso denegado');
        }

        $db = \Config\Database::connect();
        $cred = $db->table('project_credentials')->where('id', $id)->get()->getRow();

        if (!$cred) {
            return $this->failNotFound('Credencial no encontrada');
        }

        $data = [
            'company_id'   => $this->request->getVar('company_id') ?? $cred->company_id,
            'service_name' => $this->request->getVar('service_name') ?? $cred->service_name,
            'environment'  => $this->request->getVar('environment') ?? $cred->environment,
            'username'     => $this->request->getVar('username') ?? $cred->username,
            'notes'        => $this->request->getVar('notes') ?? $cred->notes,
            'updated_at'   => date('Y-m-d H:i:s')
        ];

        $secret = $this->request->getVar('secret');
        if (!empty($secret)) {
            $encData = $this->encrypt($secret);
            $data['encrypted_payload'] = $encData['payload'];
            $data['iv'] = $encData['iv'];
            $data['auth_tag'] = $encData['tag'];
        }

        $db->table('project_credentials')->where('id', $id)->update($data);

        return $this->respond(['message' => 'Credencial actualizada exitosamente']);
    }

    public function delete($id = null)
    {
        if (session()->get('role') !== 'admin') {
            return $this->failForbidden('Acceso denegado');
        }

        $db = \Config\Database::connect();
        $db->table('project_credentials')->where('id', $id)->delete();

        return $this->respondDeleted(['message' => 'Credencial eliminada']);
    }
}
