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
 * Update list of titles from AniDB.net
 *
 * @package AnimeDb\Bundle\AniDbFillerBundle\Command
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class UpdateTitlesCommand extends ContainerAwareCommand
{
    /**
     * Cache life time
     *
     * @var integer
     */
    const CACHE_LIFE_TIME = 86400;

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command.Command::configure()
     */
    protected function configure()
    {
        $this->setName('animedb:update-titles')
            ->setDescription('Update list of titles from AniDB.net');
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Console\Command.Command::execute()
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        $cache_dir = $this->getContainer()->getParameter('kernel.cache_dir').'/';
        $url = $this->getContainer()->getParameter('anime_db.ani_db.import_titles');
        $file = $cache_dir.pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
        $file_csv = $cache_dir.$this->getContainer()->getParameter('anime_db.ani_db.titles_db');

        // download db if need
        if (!file_exists($file) || filemtime($file)+self::CACHE_LIFE_TIME < time()) {
            if (@!copy($url, $file)) {
                throw new \RuntimeException('Failed to download the titles database');
            }
            $output->writeln('The titles database is loaded');
            $output->writeln('Start assembling database');

            // clear list titles and add unified title
            $fp = gzopen($file, 'r');
            $fp_csv = gzopen($file_csv, 'w');
            while (!gzeof($fp)) {
                $line = trim(gzgets($fp, 4096));
                // ignore comments
                if ($line[0] == '#') {
                    continue;
                }
                list($aid, $type, $lang, $title) = explode('|', $line);
                $lang = substr($lang, 0, 2);
                // ignore not supported locales
                if ($lang == 'x-') {
                    continue;
                }
                gzwrite($fp_csv, $aid.'|'.$type.'|'.$lang.'|'.$this->getUnifiedTitle($title).'|'.$title."\n");
            }
            gzclose($fp);
            gzclose($fp_csv);
            unlink($file);

            $output->writeln('The titles database is updated');
        } else {
            $output->writeln('Update is not needed');
        }
    }

    /**
     * Get unified title
     *
     * @param string $title
     * @return string
     */
    protected function getUnifiedTitle($title)
    {
        $title = mb_strtolower($title, 'utf8');
        $title = preg_replace('/\W+/u', ' ', $title);
        return trim($title);
    }
}