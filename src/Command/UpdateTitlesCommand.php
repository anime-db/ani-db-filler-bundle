<?php
/**
 * AnimeDb package.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */
namespace AnimeDb\Bundle\AniDbFillerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Update list of titles from AniDB.net.
 */
class UpdateTitlesCommand extends ContainerAwareCommand
{
    /**
     * @var int
     */
    const CACHE_LIFE_TIME = 86400;

    protected function configure()
    {
        $this->setName('animedb:update-titles')
            ->setDescription('Update list of titles from AniDB.net');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = time();
        $file_csv = $this->getContainer()->getParameter('kernel.cache_dir').'/'.
            $this->getContainer()->getParameter('anime_db.ani_db.titles_db');

        if (!file_exists($file_csv) || filemtime($file_csv) + self::CACHE_LIFE_TIME < $now) {
            try {
                $file = $this->getOriginDb($output, $now);
            } catch (\Exception $e) {
                $output->writeln(sprintf('<error>AniDB list titles is not downloaded: %s</error>', $e->getMessage()));

                return 0;
            }

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
            touch($file, $now);
            touch($file_csv, $now);

            $output->writeln('The titles database is updated');
        } else {
            $output->writeln('Update is not needed');
        }

        return 0;
    }

    /**
     * Get original db file.
     *
     * Download the original db if need and cache it in a system temp dir
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @param OutputInterface $output
     * @param int $now
     *
     * @return string
     */
    protected function getOriginDb(OutputInterface $output, $now)
    {
        $url = $this->getContainer()->getParameter('anime_db.ani_db.import_titles');

        if (($path = parse_url($url, PHP_URL_PATH)) === false) {
            throw new \InvalidArgumentException('Failed parse URL: '.$url);
        }

        $file = sys_get_temp_dir().'/'.pathinfo($path, PATHINFO_BASENAME);

        if (!file_exists($file) || filemtime($file) + self::CACHE_LIFE_TIME < $now) {
            /* @var $downloader \AnimeDb\Bundle\AppBundle\Service\Downloader */
            $downloader = $this->getContainer()->get('anime_db.downloader');
            if (!$downloader->download($url, $file)) {
                throw new \RuntimeException('Failed to download the titles database');
            }
            $output->writeln('The titles database is loaded');
        }

        return $file;
    }

    /**
     * @param string $title
     *
     * @return string
     */
    protected function getUnifiedTitle($title)
    {
        $title = mb_strtolower($title, 'utf8');
        $title = preg_replace('/\W+/u', ' ', $title);

        return trim($title);
    }
}
