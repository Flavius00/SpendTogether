<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250805064029 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE family_id_seq CASCADE');
        $this->addSql('DROP SEQUENCE user_id_seq CASCADE');
        $this->addSql('ALTER TABLE "user" DROP CONSTRAINT fk_8d93d649c35e566a');
        $this->addSql('DROP TABLE family');
        $this->addSql('DROP TABLE "user"');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE family_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE SEQUENCE user_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE family (id SERIAL NOT NULL, name VARCHAR(75) NOT NULL, monthly_target_budget NUMERIC(10, 2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE "user" (id SERIAL NOT NULL, family_id INT DEFAULT NULL, name VARCHAR(100) NOT NULL, email VARCHAR(75) NOT NULL, password VARCHAR(255) NOT NULL, user_role VARCHAR(75) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_8d93d649c35e566a ON "user" (family_id)');
        $this->addSql('ALTER TABLE "user" ADD CONSTRAINT fk_8d93d649c35e566a FOREIGN KEY (family_id) REFERENCES family (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
