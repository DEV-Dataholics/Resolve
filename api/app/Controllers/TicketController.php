<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class TicketController extends BaseController
{
    use ResponseTrait;

    private function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    private function toBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private function getRoutingSettings($db): array
    {
        $settings = [
            'intake_team_enabled'               => false,
            'intake_team_id'                    => null,
            'ticket_email_notifications_enabled' => false,
        ];

        if (!$db->tableExists('resolve_settings')) {
            return $settings;
        }

        $rows = $db->table('resolve_settings')
            ->whereIn('setting_key', [
                'intake_team_enabled',
                'intake_team_id',
                'ticket_email_notifications_enabled',
            ])
            ->get()->getResult();

        foreach ($rows as $row) {
            if ($row->setting_key === 'intake_team_enabled') {
                $settings['intake_team_enabled'] = $this->toBool($row->setting_value);
            }

            if ($row->setting_key === 'ticket_email_notifications_enabled') {
                $settings['ticket_email_notifications_enabled'] = $this->toBool($row->setting_value);
            }

            if ($row->setting_key === 'intake_team_id') {
                $settings['intake_team_id'] = is_numeric($row->setting_value)
                    ? (int) $row->setting_value
                    : null;
            }
        }

        if ($settings['intake_team_enabled'] && $settings['intake_team_id'] !== null && $db->tableExists('teams')) {
            $team = $db->table('teams')->where('id', $settings['intake_team_id'])->get()->getRow();
            if (!$team) {
                $settings['intake_team_enabled'] = false;
                $settings['intake_team_id']      = null;
            }
        }

        return $settings;
    }

    private function createTeamAlerts($db, int $ticketId, int $teamId): array
    {
        if (!$db->tableExists('team_members') || !$db->tableExists('ticket_alerts')) {
            return [];
        }

        $members = $db->query(
            'SELECT u.id as user_id, u.name, u.email, tm.notify_email
             FROM team_members tm
             INNER JOIN users u ON u.id = tm.user_id
             WHERE tm.team_id = ?
               AND tm.status = "active"
               AND u.status = "active"',
            [$teamId]
        )->getResult();

        if (empty($members)) {
            return [];
        }

        $alerts = [];
        foreach ($members as $member) {
            $alerts[] = [
                'ticket_id'   => $ticketId,
                'user_id'     => $member->user_id,
                'alert_type'  => 'ticket_created',
                'is_read'     => 0,
                'created_at'  => date('Y-m-d H:i:s'),
            ];
        }

        $db->table('ticket_alerts')->insertBatch($alerts);

        return $members;
    }

    private function sendTicketCreatedEmails(array $members, int $ticketId, string $title): void
    {
        if (empty($members)) {
            return;
        }

        $ticketUrl = base_url('servicedesk.html');

        foreach ($members as $member) {
            if (empty($member->email) || !$this->toBool($member->notify_email)) {
                continue;
            }

            try {
                $emailService = \Config\Services::email();
                $emailService->setFrom('no-reply@dataholics.com.mx', 'Dataholics Resolve');
                $emailService->setTo($member->email);
                $emailService->setSubject("Nuevo ticket #{$ticketId} asignado a tu equipo");
                $emailService->setMessage("\n                    <p>Hola {$member->name},</p>\n                    <p>Se creo un nuevo ticket y fue asignado al equipo receptor.</p>\n                    <p><strong>Ticket #{$ticketId}:</strong> {$title}</p>\n                    <p><a href='{$ticketUrl}' style='background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;'>\n                       Ver ticket en Resolve\n                    </a></p>\n                    <hr>\n                    <small>Dataholics Resolve</small>\n                ");
                $emailService->setMailType('html');
                $emailService->send();
            } catch (\Throwable $e) {
                log_message('error', 'Ticket notification email failed: ' . $e->getMessage());
            }
        }
    }

    private function isTeamMember($db, int $teamId, int $userId): bool
    {
        if (!$db->tableExists('team_members')) {
            return true;
        }

        return $db->table('team_members')
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->countAllResults() > 0;
    }

    private function sendResolvedTicketEmail($db, int $ticketId, ?string $resolutionComment = null): void
    {
        $ticket = $db->table('tickets t')
            ->select('t.id, t.title, t.description, t.priority, t.status, t.creator_id, t.resolved_at, u.name as creator_name, u.email as creator_email')
            ->join('users u', 'u.id = t.creator_id', 'left')
            ->where('t.id', $ticketId)
            ->get()->getRow();

        if (!$ticket || empty($ticket->creator_email)) {
            return;
        }

        $comments = $db->table('comments cm')
            ->select('cm.text, cm.created_at, u.name as author_name')
            ->join('users u', 'u.id = cm.author_id', 'left')
            ->where('cm.ticket_id', $ticketId)
            ->orderBy('cm.created_at', 'DESC')
            ->limit(5)
            ->get()->getResult();

        $comments = array_reverse($comments);
        $commentsHtml = '';
        foreach ($comments as $comment) {
            $author = $this->escapeHtml((string) ($comment->author_name ?? 'Soporte'));
            $text   = nl2br($this->escapeHtml((string) ($comment->text ?? '')));
            $date   = $this->escapeHtml((string) ($comment->created_at ?? ''));
            $commentsHtml .= "<li><strong>{$author}</strong> ({$date})<br>{$text}</li>";
        }

        $resolutionBlock = '';
        if (!empty($resolutionComment)) {
            $safeResolution = nl2br($this->escapeHtml($resolutionComment));
            $resolutionBlock = "<p><strong>Comentario de resolución:</strong><br>{$safeResolution}</p>";
        }

        $ticketTitle = $this->escapeHtml((string) $ticket->title);
        $ticketUrl   = base_url('client.html');
        $creatorName = $this->escapeHtml((string) ($ticket->creator_name ?? 'usuario'));

        try {
            $emailService = \Config\Services::email();
            $emailService->setFrom('no-reply@dataholics.com.mx', 'Dataholics Resolve');
            $emailService->setTo((string) $ticket->creator_email);
            $emailService->setSubject("Tu ticket #{$ticketId} fue resuelto");
            $emailService->setMessage("\n                <p>Hola {$creatorName},</p>\n                <p>Tu ticket <strong>#{$ticketId} - {$ticketTitle}</strong> fue marcado como <strong>Resuelto</strong>.</p>\n                {$resolutionBlock}\n                <p><strong>Comentarios recientes del ticket:</strong></p>\n                <ul>{$commentsHtml}</ul>\n                <p><a href='{$ticketUrl}' style='background:#3b82f6;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;'>\n                   Ver ticket en Resolve\n                </a></p>\n                <hr>\n                <small>Dataholics Resolve</small>\n            ");
            $emailService->setMailType('html');
            $emailService->send();
        } catch (\Throwable $e) {
            log_message('error', 'Ticket resolved notification email failed: ' . $e->getMessage());
        }
    }

    public function index()
    {
        $companyId  = session()->get('company_id');
        $db         = \Config\Database::connect();

        // Verificar si el usuario pertenece a una empresa interna (Dataholics)
        $company    = $db->table('companies')->where('id', $companyId)->get()->getRow();
        $isInternal = $company && (bool) $company->is_internal;

        $hasTeamsTable = $db->tableExists('teams');
        $select        = 't.*, u.name as creator_name, c.name as company_name, a.name as agent_name';
        $select       .= $hasTeamsTable ? ', tm.name as team_name' : ', NULL as team_name';

        $builder = $db->table('tickets t')
            ->select($select, false)
            ->join('users u', 'u.id = t.creator_id', 'left')
            ->join('companies c', 'c.id = t.company_id', 'left')
            ->join('users a', 'a.id = t.assigned_agent_id', 'left');

        if ($hasTeamsTable) {
            $builder->join('teams tm', 'tm.id = t.assigned_team_id', 'left');
        }

        // Tenant Isolation: solo empresas internas (Dataholics) ven todos los tickets
        if (!$isInternal) {
            $builder->where('t.company_id', $companyId);
            
            if (session()->get('role') === 'client') {
                $builder->where('t.creator_id', session()->get('user_id'));
            }
        }

        $tickets = $builder->orderBy('t.created_at', 'DESC')->get()->getResult();

        return $this->respond($tickets);
    }

    public function show($id = null)
    {
        $db     = \Config\Database::connect();
        $hasTeamsTable = $db->tableExists('teams');
        $select        = 't.*, u.name as creator_name, c.name as company_name';
        $select       .= $hasTeamsTable ? ', tm.name as team_name' : ', NULL as team_name';

        $builder = $db->table('tickets t')
            ->select($select, false)
            ->join('users u', 'u.id = t.creator_id', 'left')
            ->join('companies c', 'c.id = t.company_id', 'left')
            ->where('t.id', $id);

        if ($hasTeamsTable) {
            $builder->join('teams tm', 'tm.id = t.assigned_team_id', 'left');
        }

        $ticket = $builder->get()->getRow();

        if (!$ticket) {
            return $this->failNotFound('Ticket no encontrado');
        }

        // Tenant Isolation: verificar pertenencia a empresa interna
        $companyId  = session()->get('company_id');
        $db2        = \Config\Database::connect();
        $myCompany  = $db2->table('companies')->where('id', $companyId)->get()->getRow();
        $isInternal = $myCompany && (bool) $myCompany->is_internal;

        if (!$isInternal) {
            if ($ticket->company_id != $companyId) {
                return $this->failForbidden('No tienes permiso para ver este ticket');
            }
            if (session()->get('role') === 'client' && $ticket->creator_id != session()->get('user_id')) {
                return $this->failForbidden('No tienes permiso para consultar el detalle de este ticket');
            }
        }

        // Obtener comentarios con nombre del autor
        $comments = $db->table('comments cm')
            ->select('cm.*, u.name as author_name')
            ->join('users u', 'u.id = cm.author_id', 'left')
            ->where('cm.ticket_id', $id)
            ->orderBy('cm.created_at', 'ASC')
            ->get()->getResult();

        $ticket->comments = $comments;

        return $this->respond($ticket);
    }

    public function create()
    {
        $rules = [
            'title'       => 'required|min_length[5]',
            'description' => 'required',
            'priority'    => 'required|in_list[low,medium,high,critical]'
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $userId    = session()->get('user_id');
        $companyId = session()->get('company_id');

        $data = [
            'company_id'  => $companyId,
            'creator_id'  => $userId,
            'title'       => $this->request->getVar('title'),
            'description' => $this->request->getVar('description'),
            'priority'    => $this->request->getVar('priority'),
            'type'        => ($companyId == 1) ? 'internal' : 'external',
            'status'      => 'new'
        ];

        $db = \Config\Database::connect();
        $routingSettings = $this->getRoutingSettings($db);
        $canAssignTeam   = $db->fieldExists('assigned_team_id', 'tickets');

        if ($canAssignTeam && $routingSettings['intake_team_enabled'] && $routingSettings['intake_team_id'] !== null) {
            $data['assigned_team_id'] = $routingSettings['intake_team_id'];
        }

        if ($db->fieldExists('assigned_agent_id', 'tickets')) {
            $data['assigned_agent_id'] = null;
        }

        $db->transStart();
        $db->table('tickets')->insert($data);
        $ticketId = $db->insertID();

        $notifiedMembers = [];
        if ($canAssignTeam && isset($data['assigned_team_id'])) {
            $notifiedMembers = $this->createTeamAlerts($db, $ticketId, (int) $data['assigned_team_id']);
        }
        $db->transComplete();

        if (!$db->transStatus()) {
            return $this->fail('No fue posible crear el ticket en este momento');
        }

        if ($routingSettings['ticket_email_notifications_enabled']) {
            $this->sendTicketCreatedEmails($notifiedMembers, $ticketId, $data['title']);
        }

        return $this->respondCreated([
            'message'          => 'Ticket creado con éxito',
            'id'               => $ticketId,
            'assigned_team_id' => $data['assigned_team_id'] ?? null,
        ]);
    }

    public function update($id = null)
    {
        // Only servicedesk and admin can update ticket properties
        $role = session()->get('role');
        if ($role === 'client') {
            return $this->failForbidden('No tienes permisos para actualizar tickets');
        }

        $payload = $this->request->getJSON(true);
        if (!is_array($payload)) {
            $payload = [];
        }

        $allowed = ['status', 'priority', 'assigned_agent_id', 'assigned_team_id'];
        $data    = [];

        foreach ($allowed as $field) {
            $val = array_key_exists($field, $payload) ? $payload[$field] : $this->request->getVar($field);
            if ($val !== null) $data[$field] = $val;
        }

        $resolutionComment = trim((string) (array_key_exists('resolution_comment', $payload)
            ? $payload['resolution_comment']
            : ($this->request->getVar('resolution_comment') ?? '')));

        if (empty($data)) {
            return $this->fail('No hay datos para actualizar');
        }

        $db     = \Config\Database::connect();
        $ticket = $db->table('tickets')->where('id', $id)->get()->getRow();
        if (!$ticket) {
            return $this->failNotFound('Ticket no encontrado');
        }

        if (isset($data['assigned_team_id']) && $db->fieldExists('assigned_agent_id', 'tickets') && !array_key_exists('assigned_agent_id', $data)) {
            $currentTeamId = isset($ticket->assigned_team_id) ? (int) $ticket->assigned_team_id : null;
            $newTeamId     = $data['assigned_team_id'] !== '' ? (int) $data['assigned_team_id'] : null;

            if ($newTeamId !== $currentTeamId) {
                $data['assigned_agent_id'] = null;
            }
        }

        if (array_key_exists('assigned_agent_id', $data) && $data['assigned_agent_id'] !== null && $data['assigned_agent_id'] !== '') {
            $teamIdForValidation = array_key_exists('assigned_team_id', $data)
                ? (int) $data['assigned_team_id']
                : (isset($ticket->assigned_team_id) ? (int) $ticket->assigned_team_id : 0);

            $currentUserId   = (int) session()->get('user_id');
            $assignedAgentId = (int) $data['assigned_agent_id'];
            $isAdmin         = session()->get('role') === 'admin';

            if (!$isAdmin && $assignedAgentId === $currentUserId && $teamIdForValidation > 0 && !$this->isTeamMember($db, $teamIdForValidation, $currentUserId)) {
                return $this->failForbidden('No eres miembro activo del equipo asignado');
            }
        }

        // Set resolved_at timestamp if status becomes resolved
        $statusTransitionToResolved = isset($data['status']) && $data['status'] === 'resolved' && $ticket->status !== 'resolved';

        if ($statusTransitionToResolved) {
            $data['resolved_at'] = date('Y-m-d H:i:s');

            if ($resolutionComment !== '') {
                $db->table('comments')->insert([
                    'ticket_id' => $id,
                    'author_id' => session()->get('user_id'),
                    'text'      => $resolutionComment,
                ]);
                $data['updated_at'] = date('Y-m-d H:i:s');
            }
        }

        $db->table('tickets')->where('id', $id)->update($data);

        if ($statusTransitionToResolved) {
            $this->sendResolvedTicketEmail($db, (int) $id, $resolutionComment !== '' ? $resolutionComment : null);
        }

        return $this->respond(['message' => 'Ticket actualizado']);
    }

    public function comment($ticketId = null)
    {
        $text = $this->request->getVar('text');

        if (!$text || !trim($text)) {
            return $this->fail('El comentario no puede estar vacío');
        }

        // Validate access
        $db     = \Config\Database::connect();
        $ticket = $db->table('tickets')->where('id', $ticketId)->get()->getRow();

        if (!$ticket) {
            return $this->failNotFound('Ticket no encontrado');
        }

        // Tenant Isolation para comentarios
        $companyId  = session()->get('company_id');
        $myCompany  = $db->table('companies')->where('id', $companyId)->get()->getRow();
        $isInternal = $myCompany && (bool) $myCompany->is_internal;

        if (!$isInternal && $ticket->company_id != $companyId) {
            return $this->failForbidden('No tienes permiso para comentar en este ticket');
        }

        $data = [
            'ticket_id' => $ticketId,
            'author_id' => session()->get('user_id'),
            'text'      => $text
        ];

        $db->table('comments')->insert($data);

        // Actualizar updated_at del ticket
        $db->table('tickets')->where('id', $ticketId)->update(['updated_at' => date('Y-m-d H:i:s')]);

        $insertId = $db->insertID();

        $newComment = $db->table('comments cm')
            ->select('cm.*, u.name as author_name')
            ->join('users u', 'u.id = cm.author_id', 'left')
            ->where('cm.id', $insertId)
            ->get()->getRow();

        return $this->respondCreated([
            'message' => 'Comentario añadido', 
            'id' => $insertId,
            'comment' => $newComment
        ]);
    }

    public function updateComment($commentId = null)
    {
        $text = $this->request->getVar('text');
        if (!$text || !trim($text)) {
            return $this->fail('El comentario no puede estar vacío');
        }

        $db = \Config\Database::connect();
        $comment = $db->table('comments')->where('id', $commentId)->get()->getRow();

        if (!$comment) {
            return $this->failNotFound('Comentario no encontrado');
        }

        if ($comment->author_id != session()->get('user_id') && session()->get('role') !== 'admin') {
            return $this->failForbidden('No tienes permiso para editar este comentario');
        }

        $db->table('comments')->where('id', $commentId)->update([
            'text' => $text,
            'updated_at' => date('Y-m-d H:i:s') // Assuming there is an updated_at, if not it's fine
        ]);

        return $this->respond(['message' => 'Comentario actualizado']);
    }

    public function deleteComment($commentId = null)
    {
        $db = \Config\Database::connect();
        $comment = $db->table('comments')->where('id', $commentId)->get()->getRow();

        if (!$comment) {
            return $this->failNotFound('Comentario no encontrado');
        }

        if ($comment->author_id != session()->get('user_id') && session()->get('role') !== 'admin') {
            return $this->failForbidden('No tienes permiso para eliminar este comentario');
        }

        $db->table('comments')->where('id', $commentId)->delete();

        return $this->respondDeleted(['message' => 'Comentario eliminado']);
    }

    public function uploadAttachment()
    {
        $file = $this->request->getFile('image');
        if (!$file || !$file->isValid()) {
            return $this->fail('Archivo inválido o no se recibió imagen');
        }

        // Validate it's an image
        if (!str_starts_with($file->getMimeType(), 'image/')) {
            return $this->fail('Solo se permiten imágenes');
        }

        if (!$file->hasMoved()) {
            $newName = $file->getRandomName();
            $file->move(FCPATH . 'uploads/tickets', $newName);
            
            return $this->respond([
                'url' => '/uploads/tickets/' . $newName
            ]);
        }
        
        return $this->fail('No se pudo guardar la imagen');
    }
}
