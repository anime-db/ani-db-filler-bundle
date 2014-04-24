<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\AniDbFillerBundle\DoctrineMigrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20140424162725_CreateTableTitle extends AbstractMigration
{
    /**
     * (non-PHPdoc)
     * @see \Doctrine\DBAL\Migrations\AbstractMigration::up()
     */
    public function up(Schema $schema)
    {
        // create tables
        $this->addSql('CREATE TABLE ani_db_title (
            aid INTEGER NOT NULL,
            type INTEGER NOT NULL,
            language VARCHAR(2) NOT NULL,
            title VARCHAR(256) NOT NULL,
            PRIMARY KEY(aid, type, language, title)
        )');
        // add index
        $this->addSql('CREATE INDEX ani_db_title_aid_idx ON ani_db_title (aid)');
        $this->addSql('CREATE INDEX ani_db_title_title_idx ON ani_db_title (title)');
    }

    /**
     * (non-PHPdoc)
     * @see \Doctrine\DBAL\Migrations\AbstractMigration::down()
     */
    public function down(Schema $schema)
    {
        $schema->dropTable('ani_db_title');
    }
}