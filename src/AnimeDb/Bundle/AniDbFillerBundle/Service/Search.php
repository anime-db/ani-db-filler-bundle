<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\AniDbFillerBundle\Service;

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Search as SearchPlugin;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item as ItemSearch;
use AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser;

/**
 * Search from site AniDB.net
 * 
 * @link http://anidb.net/
 * @package AnimeDb\Bundle\AniDbFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Search extends SearchPlugin
{
    /**
     * Name
     *
     * @var string
     */
    const NAME = 'anidb';

    /**
     * Title
     *
     * @var string
     */
    const TITLE = 'AniDB.net';

    /**
     * Item link
     *
     * @var string
     */
    const ITEM_LINK = 'http://anidb.net/perl-bin/animedb.pl?show=anime&aid=#ID#';

    /**
     * Titeles DB
     *
     * @var string
     */
    protected $titles_db;

    /**
     * Locale
     *
     * @var string
     */
    protected $locale;

    /**
     * Construct
     *
     * @param string $import_titles
     * @param string $cache_dir
     * @param string $locale
     */
    public function __construct($import_titles, $cache_dir, $locale) {
        $this->locale = $locale;
        $this->titles_db = $cache_dir.'/'.pathinfo(parse_url($import_titles, PHP_URL_PATH), PATHINFO_BASENAME);
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName() {
        return self::NAME;
    }

    /**
     * Get title
     *
     * @return string
     */
    public function getTitle() {
        return self::TITLE;
    }

    /**
     * Search source by name
     *
     * Return structure
     * <code>
     * [
     *     \AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item
     * ]
     * </code>
     *
     * @param array $data
     *
     * @return array
     */
    public function search(array $data)
    {
        if (!file_exists($this->titles_db)) {
            return [];
        }

        $search = $this->getUnifiedTitle($data['name']);
        $items = [];
        $fp = gzopen($this->titles_db, 'r');
        while (!gzeof($fp)) {
            $line = trim(gzgets($fp, 4096));
            if ($line[0] == '#') {
                continue;
            }
            list($aid, $type, $lang, $title) = explode('|', $line);
            $lang = substr($lang, 0, 2);
            if ($lang == 'x-') {
                continue;
            }
            if (mb_strpos($this->getUnifiedTitle($title), $search, 0, 'utf8') === 0) {
                if ($type == 1 || ($type == 4 && $lang == $this->locale)) {
                    $items[$aid] = new ItemSearch($title, str_replace('#ID#', $aid, self::ITEM_LINK), '', '');
                } elseif (empty($titles[$aid])) {
                    $items[$aid] = new ItemSearch($title, str_replace('#ID#', $aid, self::ITEM_LINK), '', '');
                }
            }
        }
        gzclose($fp);

        return $items;
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