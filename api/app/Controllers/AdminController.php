<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class AdminController extends BaseController
{
    use ResponseTrait;

    private function parseBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function upsertSetting($db, string $key, string $value): void
    {
        if (!$db->tableExists('resolve_settings')) {
            return;
        }

        $existing = $db->table('resolve_settings')->where('setting_key', $key)->get()->getRow();
        $payload  = [
            'setting_value' => $value,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $db->table('resolve_settings')->where('id', $existing->id)->update($payload);
            return;
        }

        $payload['setting_key'] = $key;
        $payload['created_at']  = date('Y-m-d H:i:s');
        $db->table('resolve_settings')->insert($payload);
    }

    // ------------------------------------------------
    // Helper: genera un slug único a partir del nombre
    // ------------------------------------------------
    private function generateSlug(string $name, ?int $excludeId = null): string
    {
        $base = strtolower(trim($name));
        $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $base);
        $base = preg_replace('/[^a-z0-9]+/', '-', $base);
        $base = trim($base, '-');

        $db   = \Config\Database::connect();
        $slug = $base;
        $i    = 1;

        while (true) {
            $builder = $db->table('companies')->where('slug', $slug);
            if ($excludeId !== null) {
                $builder->where('id !=', $excludeId);
            }
            if ($builder->countAllResults() === 0) {
                break;
            }
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    // ------------------------------------------------
    // COMPANIES
    // ------------------------------------------------
    public function listCompanies()
    {
        $db = \Config\Database::connect();
        return $this->respond($db->table('companies')->get()->getResult());
    }

    public function createCompany()
    {
        $name = $this->request->getVar('name');
        if (!$name) {
            return $this->fail('El nombre de la empresa es requerido');
        }

        $db   = \Config\Database::connect();
        $slug = $this->generateSlug($name);

        $data = [
            'name'        => $name,
            'slug'        => $slug,
            'logo_url'    => $this->request->getVar('logo_url'),
            'brand_color' => $this->request->getVar('brand_color') ?: '#2563eb',
            'status'      => 'active'
        ];
        $db->table('companies')->insert($data);

        $id = $db->insertID();
        return $this->respondCreated([
            'id'      => $id,
            'slug'    => $slug,
            'message' => 'Empresa creada'
        ]);
    }

    public function updateCompany($id)
    {
        $db   = \Config\Database::connect();
        $data = [];

        if ($this->request->getVar('status'))      $data['status']      = $this->request->getVar('status');
        if ($this->request->getVar('name'))        $data['name']        = $this->request->getVar('name');
        if (isset($_POST['logo_url']))             $data['logo_url']    = $this->request->getVar('logo_url');
        if ($this->request->getVar('brand_color')) $data['brand_color'] = $this->request->getVar('brand_color');

        // Slug: editable por el admin, validar unicidad
        $newSlug = $this->request->getVar('slug');
        if ($newSlug !== null) {
            $cleanSlug = strtolower(trim($newSlug));
            $cleanSlug = preg_replace('/[^a-z0-9\-]+/', '-', $cleanSlug);
            $cleanSlug = trim($cleanSlug, '-');

            if ($cleanSlug === '') {
                return $this->fail('El slug no puede estar vacío');
            }

            // Verificar unicidad excluyendo la empresa actual
            $conflict = $db->table('companies')
                ->where('slug', $cleanSlug)
                ->where('id !=', $id)
                ->countAllResults();

            if ($conflict > 0) {
                return $this->fail('Ese slug ya está en uso por otra empresa');
            }

            $data['slug'] = $cleanSlug;
        }

        if (empty($data)) {
            return $this->fail('No se enviaron datos para actualizar');
        }

        $db->table('companies')->where('id', $id)->update($data);
        return $this->respond(['message' => 'Empresa actualizada']);
    }

    // ------------------------------------------------
    // PUBLIC: Datos de empresa por slug (para portal login)
    // ------------------------------------------------
    public function getCompanyBySlug($slug)
    {
        $db      = \Config\Database::connect();
        $company = $db->table('companies')
            ->select('id, name, slug, status, logo_url, brand_color')
            ->where('slug', $slug)
            ->get()->getRow();

        if (!$company) {
            return $this->failNotFound('Portal no encontrado');
        }

        if ($company->status !== 'active') {
            return $this->failForbidden('Este portal está desactivado. Contacta a Dataholics.');
        }

        return $this->respond([
            'id'          => $company->id,
            'name'        => $company->name,
            'slug'        => $company->slug,
            'logo_url'    => $company->logo_url,
            'brand_color' => $company->brand_color
        ]);
    }

    // ------------------------------------------------
    // DELETE COMPANY
    // ------------------------------------------------
    public function deleteCompany($id)
    {
        $db      = \Config\Database::connect();
        $company = $db->table('companies')->where('id', $id)->get()->getRow();

        if (!$company) {
            return $this->failNotFound('Empresa no encontrada');
        }

        // Proteger empresa interna (Dataholics)
        if ($company->is_internal) {
            return $this->failForbidden('No puedes eliminar una empresa interna');
        }

        // Verificar si tiene usuarios activos
        $userCount = $db->table('users')->where('company_id', $id)->countAllResults();
        if ($userCount > 0) {
            return $this->fail("No se puede eliminar: la empresa tiene {$userCount} usuario(s). Desactívalos primero.");
        }

        // Verificar si tiene tickets
        $ticketCount = $db->table('tickets')->where('company_id', $id)->countAllResults();
        if ($ticketCount > 0) {
            return $this->fail("No se puede eliminar: la empresa tiene {$ticketCount} ticket(s) asociado(s).");
        }

        $db->table('companies')->where('id', $id)->delete();
        return $this->respondDeleted(['message' => 'Empresa eliminada correctamente']);
    }

    // ------------------------------------------------
    // USERS
    // ------------------------------------------------
    public function listUsers()
    {
        $db    = \Config\Database::connect();
        $users = $db->query('
            SELECT u.id, u.name, u.email, u.role, u.status, u.company_id, c.name as company_name
            FROM users u
            LEFT JOIN companies c ON c.id = u.company_id
            ORDER BY u.name ASC
        ')->getResult();
        return $this->respond($users);
    }

    public function createUser()
    {
        $rules = [
            'name'       => 'required',
            'email'      => 'required|valid_email|is_unique[users.email]',
            'password'   => 'required|min_length[8]',
            'role'       => 'required|in_list[admin,servicedesk,client,client_admin]',
            'company_id' => 'required|integer'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $role  = $this->request->getVar('role');
        $email = $this->request->getVar('email');

        // Validar que admin y servicedesk tengan correo de dataholics
        if (($role === 'admin' || $role === 'servicedesk') && !str_ends_with($email, '@dataholics.com.mx')) {
            return $this->fail('Los usuarios Admin y ServiceDesk deben tener un correo @dataholics.com.mx');
        }

        $db         = \Config\Database::connect();
        $companyId  = (int) $this->request->getVar('company_id');
        $companyRow = $db->table('companies')->where('id', $companyId)->get()->getRow();
        if (!$companyRow) {
            return $this->failValidationErrors(['company_id' => 'La empresa seleccionada no existe']);
        }

        $data = [
            'company_id' => $companyId,
            'name'       => $this->request->getVar('name'),
            'email'      => $email,
            'password'   => password_hash($this->request->getVar('password'), PASSWORD_DEFAULT),
            'role'       => $this->request->getVar('role'),
            'status'     => 'active'
        ];

        try {
            $db->table('users')->insert($data);
        } catch (\Throwable $e) {
            log_message('error', 'Create user failed: ' . $e->getMessage());
            return $this->fail('No se pudo crear el usuario. Verifica que el correo no esté duplicado.');
        }

        return $this->respondCreated(['id' => $db->insertID(), 'message' => 'Usuario creado']);
    }

    public function updateUser($id)
    {
        $db   = \Config\Database::connect();

        // Verificar que el usuario existe
        $user = $db->table('users')->where('id', $id)->get()->getRow();
        if (!$user) {
            return $this->failNotFound('Usuario no encontrado');
        }

        $oldRole  = $user->role;
        $oldEmail = $user->email;

        $newRole  = $this->request->getVar('role') ?: $oldRole;
        $newEmail = $this->request->getVar('email') ?: $oldEmail;

        if (($newRole === 'admin' || $newRole === 'servicedesk') && !str_ends_with($newEmail, '@dataholics.com.mx')) {
            return $this->fail('Los usuarios Admin y ServiceDesk deben tener un correo @dataholics.com.mx');
        }

        $data = [];
        if ($this->request->getVar('name'))        $data['name']       = $this->request->getVar('name');
        if ($this->request->getVar('email'))       $data['email']      = $this->request->getVar('email');
        if ($this->request->getVar('role'))        $data['role']       = $this->request->getVar('role');
        if ($this->request->getVar('company_id'))  $data['company_id'] = (int) $this->request->getVar('company_id');
        if ($this->request->getVar('status'))      $data['status']     = $this->request->getVar('status');

        // Contraseña es opcional — solo se actualiza si se envía
        $newPassword = $this->request->getVar('password');
        if ($newPassword && strlen($newPassword) >= 8) {
            $data['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        if (empty($data)) {
            return $this->fail('No se enviaron datos para actualizar');
        }

        $db->table('users')->where('id', $id)->update($data);
        return $this->respond(['message' => 'Usuario actualizado correctamente']);
    }

    // ------------------------------------------------
    // TEAMS
    // ------------------------------------------------
    public function listTeams()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('team_members')) {
            $teams = $db->query(
                'SELECT t.*, 0 as member_count
                 FROM teams t
                 ORDER BY t.name ASC'
            )->getResult();

            return $this->respond($teams);
        }

        $teams = $db->query(
            'SELECT t.*, COUNT(tm.id) as member_count
             FROM teams t
             LEFT JOIN team_members tm ON tm.team_id = t.id AND tm.status = "active"
             GROUP BY t.id
             ORDER BY t.name ASC'
        )->getResult();

        return $this->respond($teams);
    }

    public function createTeam()
    {
        $name = trim((string) $this->request->getVar('name'));
        if ($name === '') {
            return $this->fail('El nombre del equipo es requerido');
        }

        $db   = \Config\Database::connect();
        $data = [
            'name'        => $name,
            'description' => $this->request->getVar('description') ?? ''
        ];
        $db->table('teams')->insert($data);
        return $this->respondCreated(['id' => $db->insertID(), 'message' => 'Equipo creado']);
    }

    public function getTeamMembers($teamId)
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('team_members')) {
            return $this->respond([]);
        }

        $team = $db->table('teams')->where('id', $teamId)->get()->getRow();
        if (!$team) {
            return $this->failNotFound('Equipo no encontrado');
        }

        $members = $db->query(
            'SELECT u.id as user_id, u.name, u.email, u.role,
                    tm.id as team_member_id,
                    CASE WHEN tm.id IS NULL THEN 0 ELSE 1 END as is_member,
                    COALESCE(tm.notify_email, 1) as notify_email,
                    COALESCE(tm.status, "inactive") as membership_status
             FROM users u
             LEFT JOIN team_members tm ON tm.user_id = u.id AND tm.team_id = ?
             WHERE u.role IN ("admin", "servicedesk")
               AND u.status = "active"
             ORDER BY u.name ASC',
            [$teamId]
        )->getResult();

        return $this->respond($members);
    }

    public function setTeamMembers($teamId)
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('team_members')) {
            return $this->fail('La tabla team_members no existe. Ejecuta migraciones primero.');
        }

        $team = $db->table('teams')->where('id', $teamId)->get()->getRow();
        if (!$team) {
            return $this->failNotFound('Equipo no encontrado');
        }

        $payload = $this->request->getJSON(true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $members = $payload['members'] ?? $this->request->getVar('members');
        if (!is_array($members)) {
            return $this->failValidationErrors(['members' => 'Debes enviar un arreglo de miembros en formato JSON']);
        }

        $cleanMembers = [];
        foreach ($members as $member) {
            $userId = isset($member['user_id']) ? (int) $member['user_id'] : 0;
            if ($userId <= 0) {
                continue;
            }

            $notifyEmail = isset($member['notify_email']) ? ($this->parseBool($member['notify_email']) ? 1 : 0) : 1;
            $cleanMembers[] = [
                'team_id'      => (int) $teamId,
                'user_id'      => $userId,
                'notify_email' => $notifyEmail,
                'status'       => 'active',
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ];
        }

        $db->transStart();
        $db->table('team_members')->where('team_id', $teamId)->delete();
        if (!empty($cleanMembers)) {
            $db->table('team_members')->insertBatch($cleanMembers);
        }
        $db->transComplete();

        if (!$db->transStatus()) {
            return $this->fail('No fue posible guardar miembros del equipo');
        }

        return $this->respond(['message' => 'Miembros del equipo actualizados']);
    }

    public function getRoutingSettings()
    {
        $db = \Config\Database::connect();

        $settings = [
            'intake_team_enabled'                => false,
            'intake_team_id'                     => null,
            'ticket_email_notifications_enabled' => false,
        ];

        if (!$db->tableExists('resolve_settings')) {
            return $this->respond($settings);
        }

        $rows = $db->table('resolve_settings')
            ->whereIn('setting_key', ['intake_team_enabled', 'intake_team_id', 'ticket_email_notifications_enabled'])
            ->get()->getResult();

        foreach ($rows as $row) {
            if ($row->setting_key === 'intake_team_enabled') {
                $settings['intake_team_enabled'] = $this->parseBool($row->setting_value);
            }

            if ($row->setting_key === 'ticket_email_notifications_enabled') {
                $settings['ticket_email_notifications_enabled'] = $this->parseBool($row->setting_value);
            }

            if ($row->setting_key === 'intake_team_id' && is_numeric($row->setting_value)) {
                $settings['intake_team_id'] = (int) $row->setting_value;
            }
        }

        return $this->respond($settings);
    }

    public function updateRoutingSettings()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('resolve_settings')) {
            return $this->fail('La tabla resolve_settings no existe. Ejecuta migraciones primero.');
        }

        $enabled    = $this->parseBool($this->request->getVar('intake_team_enabled'));
        $emailAlert = $this->parseBool($this->request->getVar('ticket_email_notifications_enabled'));
        $teamIdRaw  = $this->request->getVar('intake_team_id');
        $teamId     = is_numeric($teamIdRaw) ? (int) $teamIdRaw : null;

        if ($enabled) {
            if ($teamId === null || $teamId <= 0) {
                return $this->failValidationErrors(['intake_team_id' => 'Selecciona un equipo receptor válido']);
            }

            $teamExists = $db->table('teams')->where('id', $teamId)->countAllResults() > 0;
            if (!$teamExists) {
                return $this->failValidationErrors(['intake_team_id' => 'El equipo receptor no existe']);
            }
        }

        $this->upsertSetting($db, 'intake_team_enabled', $enabled ? '1' : '0');
        $this->upsertSetting($db, 'intake_team_id', $teamId !== null ? (string) $teamId : '');
        $this->upsertSetting($db, 'ticket_email_notifications_enabled', $emailAlert ? '1' : '0');

        return $this->respond(['message' => 'Configuración de enrutamiento actualizada']);
    }
}
