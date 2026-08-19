<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;

class AgentController extends BaseController
{
    use ResponseTrait;

    public function index()
    {
        // Ruta local (Guidelines/proyectos/Dataholics Resolve/api)
        $agentsDir = realpath(ROOTPATH . '../../../agentes');
        
        // Ruta producción (noodluis/api_resolve/)
        if (!$agentsDir || !is_dir($agentsDir)) {
            $agentsDir = realpath(ROOTPATH . '../agentes');
        }

        if (!$agentsDir || !is_dir($agentsDir)) {
            return $this->fail('No se encontró el directorio de agentes. Buscado en: ' . ROOTPATH . '../../../agentes y ' . ROOTPATH . '../agentes');
        }

        $files = glob($agentsDir . '/*.md');
        $agents = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $filename = basename($file);
            
            // Extraer nombre amigable del nombre del archivo
            $title = str_replace('.agent.md', '', $filename);
            $title = str_replace('-', ' ', $title);
            $title = ucwords($title);

            $agents[] = [
                'id'       => $filename,
                'title'    => $title,
                'content'  => $content,
                'filename' => $filename
            ];
        }

        return $this->respond($agents);
    }
}
