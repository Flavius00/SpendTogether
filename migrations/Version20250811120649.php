<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250811120649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE thresholds (id SERIAL NOT NULL, category_id INT DEFAULT NULL, family_id INT DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_289835012469DE2 ON thresholds (category_id)');
        $this->addSql('CREATE INDEX IDX_2898350C35E566A ON thresholds (family_id)');
        $this->addSql('ALTER TABLE thresholds ADD CONSTRAINT FK_289835012469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE thresholds ADD CONSTRAINT FK_2898350C35E566A FOREIGN KEY (family_id) REFERENCES family (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE thresholds DROP CONSTRAINT FK_289835012469DE2');
        $this->addSql('ALTER TABLE thresholds DROP CONSTRAINT FK_2898350C35E566A');
        $this->addSql('DROP TABLE thresholds');
    }
}
