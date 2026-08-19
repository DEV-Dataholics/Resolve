<?php

namespace App\Controllers;

use App\Models\KbArticleModel;
use CodeIgniter\API\ResponseTrait;

class KbController extends BaseController
{
    use ResponseTrait;

    // -------------------------------------------------------
    // GET api/kb
    // Lista artículos publicados. Admin/servicedesk ven borradores también.
    // Soporta ?category=X y ?q=búsqueda
    // -------------------------------------------------------
    public function index()
    {
        $model    = new KbArticleModel();
        $role     = session()->get('role');
        $canDraft = in_array($role, ['admin', 'servicedesk'], true);

        $db      = \Config\Database::connect();
        $builder = $db->table('kb_articles a')
            ->select('a.id, a.title, a.slug, a.category, a.status, a.created_at, a.updated_at, u.name as author_name')
            ->join('users u', 'u.id = a.author_id', 'left');

        if (!$canDraft) {
            $builder->where('a.status', 'published');
        }

        $category = $this->request->getGet('category');
        if ($category) {
            $builder->where('a.category', $category);
        }

        $q = $this->request->getGet('q');
        if ($q) {
            $builder->groupStart()
                ->like('a.title', $q)
                ->orLike('a.content', $q)
                ->groupEnd();
        }

        $articles = $builder->orderBy('a.created_at', 'DESC')->get()->getResult();

        return $this->respond($articles);
    }

    // -------------------------------------------------------
    // GET api/kb/categories
    // Devuelve categorías únicas (solo de publicados para usuarios normales)
    // -------------------------------------------------------
    public function categories()
    {
        $role     = session()->get('role');
        $canDraft = in_array($role, ['admin', 'servicedesk'], true);

        $db      = \Config\Database::connect();
        $builder = $db->table('kb_articles')
            ->select('DISTINCT category')
            ->where('category IS NOT NULL', null, false)
            ->where('category !=', '');

        if (!$canDraft) {
            $builder->where('status', 'published');
        }

        $rows       = $builder->orderBy('category', 'ASC')->get()->getResult();
        $categories = array_column($rows, 'category');

        return $this->respond($categories);
    }

    // -------------------------------------------------------
    // GET api/kb/:id
    // Detalle de un artículo
    // -------------------------------------------------------
    public function show($id = null)
    {
        $db      = \Config\Database::connect();
        $article = $db->table('kb_articles a')
            ->select('a.*, u.name as author_name')
            ->join('users u', 'u.id = a.author_id', 'left')
            ->where('a.id', $id)
            ->get()->getRow();

        if (!$article) {
            return $this->failNotFound('Artículo no encontrado');
        }

        $role     = session()->get('role');
        $canDraft = in_array($role, ['admin', 'servicedesk'], true);

        if ($article->status === 'draft' && !$canDraft) {
            return $this->failForbidden('Este artículo no está disponible');
        }

        return $this->respond($article);
    }

    // -------------------------------------------------------
    // POST api/kb
    // Crear artículo (admin y servicedesk únicamente)
    // -------------------------------------------------------
    public function create()
    {
        $role = session()->get('role');
        if (!in_array($role, ['admin', 'servicedesk'], true)) {
            return $this->failForbidden('Solo el equipo Dataholics puede crear artículos');
        }

        $rules = [
            'title'    => 'required|min_length[3]|max_length[255]',
            'content'  => 'required|min_length[10]',
            'category' => 'permit_empty|max_length[100]',
            'status'   => 'permit_empty|in_list[draft,published]',
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $model    = new KbArticleModel();
        $title    = $this->request->getVar('title');
        $category = $this->request->getVar('category') ?? '';
        $content  = $this->request->getVar('content');
        $status   = $this->request->getVar('status') ?? 'draft';

        $slug = $model->generateSlug($title);

        $id = $model->insert([
            'title'     => $title,
            'slug'      => $slug,
            'category'  => $category,
            'content'   => $content,
            'status'    => $status,
            'author_id' => session()->get('user_id'),
        ]);

        $article = $model->find($id);

        return $this->respondCreated($article);
    }

    // -------------------------------------------------------
    // PUT api/kb/:id
    // Actualizar artículo (admin y servicedesk únicamente)
    // -------------------------------------------------------
    public function update($id = null)
    {
        $role = session()->get('role');
        if (!in_array($role, ['admin', 'servicedesk'], true)) {
            return $this->failForbidden('Solo el equipo Dataholics puede editar artículos');
        }

        $model   = new KbArticleModel();
        $article = $model->find($id);

        if (!$article) {
            return $this->failNotFound('Artículo no encontrado');
        }

        $rules = [
            'title'    => 'permit_empty|min_length[3]|max_length[255]',
            'content'  => 'permit_empty|min_length[10]',
            'category' => 'permit_empty|max_length[100]',
            'status'   => 'permit_empty|in_list[draft,published]',
        ];

        if (!$this->validate($rules)) {
            return $this->failValidationErrors($this->validator->getErrors());
        }

        $data = array_filter([
            'title'    => $this->request->getVar('title'),
            'category' => $this->request->getVar('category'),
            'content'  => $this->request->getVar('content'),
            'status'   => $this->request->getVar('status'),
        ], fn($v) => $v !== null);

        // Regenerar slug solo si el título cambia
        if (!empty($data['title']) && $data['title'] !== $article['title']) {
            $data['slug'] = $model->generateSlug($data['title']);
        }

        $model->update($id, $data);

        return $this->respond($model->find($id));
    }

    // -------------------------------------------------------
    // DELETE api/kb/:id
    // Eliminar artículo (solo admin)
    // -------------------------------------------------------
    public function delete($id = null)
    {
        if (session()->get('role') !== 'admin') {
            return $this->failForbidden('Solo el administrador puede eliminar artículos');
        }

        $model   = new KbArticleModel();
        $article = $model->find($id);

        if (!$article) {
            return $this->failNotFound('Artículo no encontrado');
        }

        $model->delete($id);

        return $this->respondDeleted(['message' => 'Artículo eliminado']);
    }
}
