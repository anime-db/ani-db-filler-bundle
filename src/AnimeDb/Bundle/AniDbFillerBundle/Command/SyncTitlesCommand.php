<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\AniDbFillerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\EntityManager;

/**
 * Sync list of titles from AniDB.net
 *
 * @package AnimeDb\Bundle\AniDbFillerBundle\Command
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class SyncTitlesCommand extends ContainerAwareCommand
{
    /**
     * Cache life time
     *
     * @var integer
     */
    const CACHE_LIFE_TIME = 86400;

    /**
     * New table name
     *
     * @var string
     */
    const NEW_TABLE_NAME = '_new';

    /**
     * Old table name
     *
     * @var string
     */
    const OLD_TABLE_NAME = '_old';

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command.Command::configure()
     */
    protected function configure()
    {
        $this->setName('animedb:sync-titles')
            ->setDescription('Sync list of titles from AniDB.net');
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command.Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        // download db if need
        $url = $this->getContainer()->getParameter('anime_db.ani_db.import_titles');
        $file = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
        $file = $this->getContainer()->getParameter('kernel.cache_dir').'/'.$file;
        if (!file_exists($file) || filemtime($file)+self::CACHE_LIFE_TIME < time()) {
            if (@!copy($url, $file)) {
                throw new \RuntimeException('Failed to download the titles database');
            }
            $output->writeln('The titles database is updated');
        }

        /* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');

        $origin_table_name = $this->createTempTable($em);

        // read db
        $titles = [];
        $handle = gzopen($file, 'r');
        while (!gzeof($handle)) {
            $line = trim(gzgets($handle, 4096));
            if ($line[0] == '#') {
                continue;
            }
            list($aid, $type, $lang, $title) = explode('|', $line);
            if (in_array($lang, ['x-other', 'x-kot'])) {
                continue;
            }
            $lang = substr($lang, 0, 2);
            // build query
            $titles[md5($aid.'|'.$type.'|'.$lang.'|'.$title)] =
                'INSERT INTO '.self::NEW_TABLE_NAME.' VALUES ('.$aid.', '.$type.', \''.$lang.'\', \''.$title.'\');';
        }
        gzclose($handle);

        $output->writeln('Detected <info>'.count($titles).'</info> titles');

        // fill db
        $progress = $this->getHelperSet()->get('progress');
        $progress->start($output, count($titles));

        while ($titles) {
            // combine with 100 queries
            $query = '';
            for ($i = 0; $i < 100 && ($title = array_shift($titles)); $i++) {
                $query .= $title;
            }
            $em->getConnection()->exec($query);
            $progress->advance(100);
        }
        $progress->finish();

        $this->replaceTables($origin_table_name, $em);
    }

    /**
     * Create temporary table
     *
     * @param \Doctrine\ORM\EntityManager $em
     *
     * @return string
     */
    protected function createTempTable(EntityManager $em)
    {
        /* @var $metadata \Doctrine\ORM\Mapping\ClassMetadata */
        $metadata = $em->getMetadataFactory()->getMetadataFor('AnimeDb\Bundle\AniDbFillerBundle\Entity\Title');
        $origin_table_name = $metadata->getTableName();
        $metadata->setTableName(self::NEW_TABLE_NAME);

        $tool = new SchemaTool($em);
        $schema = $tool->getCreateSchemaSql([$metadata]);

        // drop index if need
        if (count($schema) > 1) {
            foreach ($schema as $query) {
                if (preg_match('/'.$origin_table_name.'([^ ]+)/', $query, $match)) {
                    $em->getConnection()->exec('DROP INDEX IF EXISTS '.$match[0]);
                }
            }
        }

        $em->getConnection()->exec('DROP TABLE IF EXISTS '.self::NEW_TABLE_NAME);

        // create table
        foreach ($schema as $query) {
            $em->getConnection()->exec($query);
        }
        return $origin_table_name;
    }

    /**
     * Replace tables
     *
     * @throws \Exception
     *
     * @param string $origin_table_name
     * @param \Doctrine\ORM\EntityManager $em
     */
    protected function replaceTables($origin_table_name, EntityManager $em)
    {
        $em->getConnection()->beginTransaction();
        try {
            $em->getConnection()->exec('ALTER TABLE '.$origin_table_name.' RENAME TO '.self::OLD_TABLE_NAME);
            $em->getConnection()->exec('ALTER TABLE '.self::NEW_TABLE_NAME.' RENAME TO '.$origin_table_name);
            $em->getConnection()->exec('DROP TABLE _old');
            $em->getConnection()->commit();
        } catch (\Exception $e) {
            $em->getConnection()->rollback();
            $em->close();
            throw $e;
        }
    }
}