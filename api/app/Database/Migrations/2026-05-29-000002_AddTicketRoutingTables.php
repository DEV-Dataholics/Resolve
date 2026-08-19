<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTicketRoutingTables extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('resolve_settings')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'setting_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'setting_value' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('setting_key');
            $this->forge->createTable('resolve_settings', true);
        }

        if (!$this->db->tableExists('team_members')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'team_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'notify_email' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'status' => [
                    'type'       => 'ENUM',
                    'constraint' => ['active', 'inactive'],
                    'default'    => 'active',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['team_id', 'user_id']);
            $this->forge->addKey('team_id');
            $this->forge->addKey('user_id');
            $this->forge->createTable('team_members', true);
        }

        if (!$this->db->tableExists('ticket_alerts')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'ticket_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'alert_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'ticket_created',
                ],
                'is_read' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'read_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('ticket_id');
            $this->forge->addKey('user_id');
            $this->forge->createTable('ticket_alerts', true);
        }

        $now = date('Y-m-d H:i:s');

        $defaults = [
            'intake_team_enabled' => '0',
            'intake_team_id' => '',
            'ticket_email_notifications_enabled' => '0',
        ];

        foreach ($defaults as $key => $value) {
            $existing = $this->db->table('resolve_settings')->where('setting_key', $key)->get()->getRow();
            if (!$existing) {
                $this->db->table('resolve_settings')->insert([
                    'setting_key'   => $key,
                    'setting_value' => $value,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('ticket_alerts')) {
            $this->forge->dropTable('ticket_alerts', true);
        }

        if ($this->db->tableExists('team_members')) {
            $this->forge->dropTable('team_members', true);
        }

        if ($this->db->tableExists('resolve_settings')) {
            $this->forge->dropTable('resolve_settings', true);
        }
    }
}
