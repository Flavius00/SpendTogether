<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250827115835 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_family_type_month_amount');
        $this->addSql('ALTER TABLE budget_alert_log ADD category_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE budget_alert_log ADD CONSTRAINT FK_8B1FBBB912469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_8B1FBBB912469DE2 ON budget_alert_log (category_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE budget_alert_log DROP CONSTRAINT FK_8B1FBBB912469DE2');
        $this->addSql('DROP INDEX IDX_8B1FBBB912469DE2');
        $this->addSql('ALTER TABLE budget_alert_log DROP category_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_family_type_month_amount ON budget_alert_log (family_id, type, month, projected_amount)');
    }
}
