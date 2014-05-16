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
use Knp\Menu\ItemInterface;

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
    const ITEM_LINK = '/perl-bin/animedb.pl?show=anime&aid=#ID#';

    /**
     * Browser
     *
     * @var \AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser
     */
    protected $browser;

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
     * @param \AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser $browser
     * @param string $titles_db
     * @param string $cache_dir
     * @param string $locale
     */
    public function __construct(Browser $browser, $titles_db, $cache_dir, $locale) {
        $this->browser = $browser;
        $this->locale = $locale;
        $this->titles_db = $cache_dir.'/'.$titles_db;
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
     * Build menu for plugin
     *
     * @param \Knp\Menu\ItemInterface $item
     *
     * @return \Knp\Menu\ItemInterface
     */
    public function buildMenu(ItemInterface $item)
    {
        return parent::buildMenu($item)
            ->setLinkAttribute('class', 'icon-label icon-label-plugin-anidb');
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
        // if the db does not exists, send a request to download
        if (!file_exists($this->titles_db)) {
            return [];
        }

        $search = $this->getUnifiedTitle($data['name']);

        // search by name
        $aids = [];
        $fp = gzopen($this->titles_db, 'r');
        while (!gzeof($fp)) {
            $line = trim(gzgets($fp, 4096));
            list($aid, $type, $lang, $unified, ) = explode('|', $line);
            if (mb_strpos($unified, $search, 0, 'utf8') === 0) {
                if ($type == 1 || ($type == 4 && $lang == $this->locale) || empty($titles[$aid])) {
                    $aids[] = $aid;
                }
            }
        }
        gzclose($fp);
        $aids = array_unique($aids);

        // get all names for aid
        $items = [];
        $fp = gzopen($this->titles_db, 'r');
        while (!gzeof($fp)) {
            $line = trim(gzgets($fp, 4096));
            list($aid, $type, $lang, , $title) = explode('|', $line);
            if (in_array($aid, $aids)) {
                $items[$aid][$lang][$type] = $title;
            }
        }
        gzclose($fp);

        // build result
        foreach ($items as $aid => $item) {
            if (!empty($item[$this->locale])) {
                $main_name = $this->getNameForLocale($this->locale, $item);
            } elseif ($this->locale != 'en' && !empty($item['en'])) {
                $main_name = $this->getNameForLocale('en', $item);
            } else {
                $main_name = $this->getNameForLocale(array_keys($item)[0], $item);
            }
            $description = [];
            foreach ($item as $names) {
                foreach ($names as $name) {
                    $description[] = $name;
                }
            }
            sort($description);
            $items[$aid] = new ItemSearch(
                $main_name,
                $this->getLinkForFill($this->browser->getHost().str_replace('#ID#', $aid, self::ITEM_LINK)),
                '',
                implode("\n", array_unique($description))
            );
        }

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

    /**
     * Get name for locale
     *
     * @param string $locale
     * @param array $names
     * @return string
     */
    protected function getNameForLocale($locale, & $names)
    {
        if (isset($names[$locale][1])) {
            $name = $names[$locale][1];
            unset($names[$locale][1]);
        } elseif (isset($names[$locale][4])) {
            $name = $names[$locale][4];
            unset($names[$locale][4]);
        } else {
            $name = array_shift($names[$locale]);
        }
        return $name;
    }
}