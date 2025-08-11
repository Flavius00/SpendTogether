<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250807180236 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE access_token (id SERIAL NOT NULL, user_obj_id INT NOT NULL, token VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_B6A2DD6835B2879 ON access_token (user_obj_id)');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT FK_B6A2DD6835B2879 FOREIGN KEY (user_obj_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE access_token DROP CONSTRAINT FK_B6A2DD6835B2879');
        $this->addSql('DROP TABLE access_token');
    }
}
