<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250808135621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE access_token DROP CONSTRAINT fk_b6a2dd6835b2879');
        $this->addSql('DROP INDEX idx_b6a2dd6835b2879');
        $this->addSql('ALTER TABLE access_token ADD expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE access_token RENAME COLUMN user_obj_id TO user_object_id');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT FK_B6A2DD6823CDDBCF FOREIGN KEY (user_object_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B6A2DD685F37A13B ON access_token (token)');
        $this->addSql('CREATE INDEX IDX_B6A2DD6823CDDBCF ON access_token (user_object_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE access_token DROP CONSTRAINT FK_B6A2DD6823CDDBCF');
        $this->addSql('DROP INDEX UNIQ_B6A2DD685F37A13B');
        $this->addSql('DROP INDEX IDX_B6A2DD6823CDDBCF');
        $this->addSql('ALTER TABLE access_token DROP expires_at');
        $this->addSql('ALTER TABLE access_token RENAME COLUMN user_object_id TO user_obj_id');
        $this->addSql('ALTER TABLE access_token ADD CONSTRAINT fk_b6a2dd6835b2879 FOREIGN KEY (user_obj_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_b6a2dd6835b2879 ON access_token (user_obj_id)');
    }
}
