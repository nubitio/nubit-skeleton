<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Nubit 1.0 records where a refresh token was issued and last used, so a
 * session list can show it and a stolen token can be spotted.
 */
final class Version20260827182523 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add session tracking columns to nubit_refresh_token (Nubit 1.0).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE nubit_refresh_token ADD user_agent VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE nubit_refresh_token ADD ip_address VARCHAR(45) DEFAULT NULL');
        $this->addSql('ALTER TABLE nubit_refresh_token ADD last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE nubit_refresh_token DROP user_agent');
        $this->addSql('ALTER TABLE nubit_refresh_token DROP ip_address');
        $this->addSql('ALTER TABLE nubit_refresh_token DROP last_used_at');
    }
}
