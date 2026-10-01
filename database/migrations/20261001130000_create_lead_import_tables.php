<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateLeadImportTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('lead_imports', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'string', ['limit' => 32, 'null' => false])
            ->addColumn('filename', 'string', ['limit' => 255])
            ->addColumn('file_size', 'biginteger', ['signed' => false])
            ->addColumn('uploaded_bytes', 'biginteger', ['signed' => false, 'default' => 0])
            ->addColumn('byte_offset', 'biginteger', ['signed' => false, 'default' => 0])
            ->addColumn('imported_rows', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('status', 'string', ['limit' => 20])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime')
            ->create();

        $this->table('imported_applications', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false, 'null' => false])
            ->addColumn('import_id', 'string', ['limit' => 32])
            ->addColumn('source_row', 'integer', ['signed' => false])
            ->addColumn('external_id', 'string', ['limit' => 64])
            ->addColumn('created_at', 'datetime')
            ->addColumn('first_name', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('last_name', 'string', ['limit' => 255])
            ->addColumn('phone', 'string', ['limit' => 32])
            ->addColumn('email', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 255])
            ->addColumn('source', 'string', ['limit' => 255])
            ->addColumn('utm_campaign', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('product', 'string', ['limit' => 255])
            ->addColumn('budget_uah', 'decimal', ['precision' => 14, 'scale' => 2, 'null' => true])
            ->addColumn('status', 'string', ['limit' => 50])
            ->addColumn('manager', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('comment', 'text', ['null' => true])
            ->addColumn('next_contact_at', 'datetime', ['null' => true])
            ->addIndex(['import_id', 'source_row'], ['unique' => true, 'name' => 'import_row_unique'])
            ->create();
    }
}
