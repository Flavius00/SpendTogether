<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250827075252 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE budget_alert_log (id SERIAL NOT NULL, family_id INT NOT NULL, type VARCHAR(32) NOT NULL, month VARCHAR(7) NOT NULL, projected_amount NUMERIC(11, 2) NOT NULL, budget_amount NUMERIC(11, 2) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_8B1FBBB9C35E566A ON budget_alert_log (family_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_family_type_month_amount ON budget_alert_log (family_id, type, month, projected_amount)');
        $this->addSql('ALTER TABLE budget_alert_log ADD CONSTRAINT FK_8B1FBBB9C35E566A FOREIGN KEY (family_id) REFERENCES family (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE budget_alert_log DROP CONSTRAINT FK_8B1FBBB9C35E566A');
        $this->addSql('DROP TABLE budget_alert_log');
    }
}
