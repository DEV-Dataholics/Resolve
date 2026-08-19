<?php

namespace App\Models;

use CodeIgniter\Model;

class CompanyModel extends Model
{
    protected $table            = 'companies';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['name', 'slug', 'status', 'is_internal'];

    // Dates
    protected $useTimestamps = false;
    protected $createdField  = 'created_at';
}
