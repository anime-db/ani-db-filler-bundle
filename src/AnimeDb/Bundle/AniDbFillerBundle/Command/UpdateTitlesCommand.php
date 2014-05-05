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
        $url = $this->getContainer()->getParameter('anime_db.ani_db.import_titles');
        $file = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME);
        $file = $this->getContainer()->getParameter('kernel.cache_dir').'/'.$file;

        // download db if need
        if (!file_exists($file) || filemtime($file)+self::CACHE_LIFE_TIME < time()) {
            if (@!copy($url, $file)) {
                throw new \RuntimeException('Failed to download the titles database');
            }
            $output->writeln('The titles database is updated');
        } else {
            $output->writeln('Update is not needed');
        }
    }
}