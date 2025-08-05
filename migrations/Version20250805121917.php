<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250805121917 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE expense ADD user_object_id INT NOT NULL');
        $this->addSql('ALTER TABLE expense ADD subscription_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA623CDDBCF FOREIGN KEY (user_object_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE expense ADD CONSTRAINT FK_2D3A8DA69A1887DC FOREIGN KEY (subscription_id) REFERENCES subscription (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_2D3A8DA623CDDBCF ON expense (user_object_id)');
        $this->addSql('CREATE INDEX IDX_2D3A8DA69A1887DC ON expense (subscription_id)');
        $this->addSql('ALTER TABLE subscription ADD user_object_id INT NOT NULL');
        $this->addSql('ALTER TABLE subscription ADD category_id INT NOT NULL');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D323CDDBCF FOREIGN KEY (user_object_id) REFERENCES "user" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE subscription ADD CONSTRAINT FK_A3C664D312469DE2 FOREIGN KEY (category_id) REFERENCES category (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_A3C664D323CDDBCF ON subscription (user_object_id)');
        $this->addSql('CREATE INDEX IDX_A3C664D312469DE2 ON subscription (category_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D323CDDBCF');
        $this->addSql('ALTER TABLE subscription DROP CONSTRAINT FK_A3C664D312469DE2');
        $this->addSql('DROP INDEX IDX_A3C664D323CDDBCF');
        $this->addSql('DROP INDEX IDX_A3C664D312469DE2');
        $this->addSql('ALTER TABLE subscription DROP user_object_id');
        $this->addSql('ALTER TABLE subscription DROP category_id');
        $this->addSql('ALTER TABLE expense DROP CONSTRAINT FK_2D3A8DA623CDDBCF');
        $this->addSql('ALTER TABLE expense DROP CONSTRAINT FK_2D3A8DA69A1887DC');
        $this->addSql('DROP INDEX IDX_2D3A8DA623CDDBCF');
        $this->addSql('DROP INDEX IDX_2D3A8DA69A1887DC');
        $this->addSql('ALTER TABLE expense DROP user_object_id');
        $this->addSql('ALTER TABLE expense DROP subscription_id');
    }
}
