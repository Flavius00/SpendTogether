<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250812084446 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP SEQUENCE thresholds_id_seq CASCADE');
        $this->addSql('CREATE TABLE threshold (id SERIAL NOT NULL, category_id INT DEFAULT NULL, family_id INT DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EB7A2A9612469DE2 ON threshold (category_id)');
        $this->addSql('CREATE INDEX IDX_EB7A2A96C35E566A ON threshold (family_id)');
        $this->addSql('ALTER TABLE threshold ADD CONSTRAINT FK_EB7A2A9612469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE threshold ADD CONSTRAINT FK_EB7A2A96C35E566A FOREIGN KEY (family_id) REFERENCES family (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE thresholds DROP CONSTRAINT fk_289835012469de2');
        $this->addSql('ALTER TABLE thresholds DROP CONSTRAINT fk_2898350c35e566a');
        $this->addSql('DROP TABLE thresholds');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('CREATE SEQUENCE thresholds_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
        $this->addSql('CREATE TABLE thresholds (id SERIAL NOT NULL, category_id INT DEFAULT NULL, family_id INT DEFAULT NULL, amount NUMERIC(10, 2) NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_289835012469de2 ON thresholds (category_id)');
        $this->addSql('CREATE INDEX idx_2898350c35e566a ON thresholds (family_id)');
        $this->addSql('ALTER TABLE thresholds ADD CONSTRAINT fk_289835012469de2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE thresholds ADD CONSTRAINT fk_2898350c35e566a FOREIGN KEY (family_id) REFERENCES family (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE threshold DROP CONSTRAINT FK_EB7A2A9612469DE2');
        $this->addSql('ALTER TABLE threshold DROP CONSTRAINT FK_EB7A2A96C35E566A');
        $this->addSql('DROP TABLE threshold');
    }
}
