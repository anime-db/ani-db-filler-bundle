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

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\Refiller as RefillerPlugin;
use AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\Item as ItemRefiller;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;

/**
 * Refiller from site AniDB.net
 * 
 * @link http://anidb.net/
 * @package AnimeDb\Bundle\AniDbFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Refiller extends RefillerPlugin
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
     * List of supported fields
     *
     * @var array
     */
    protected $supported_fields = [
        self::FIELD_DATE_END,
        self::FIELD_DATE_PREMIERE,
        self::FIELD_EPISODES,
        self::FIELD_EPISODES_NUMBER,
        self::FIELD_GENRES,
        self::FIELD_NAMES,
        self::FIELD_SOURCES,
        self::FIELD_SUMMARY
    ];

    /**
     * Browser
     *
     * @var \AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser
     */
    private $browser;

    /**
     * Filler
     *
     * @var \AnimeDb\Bundle\AniDbFillerBundle\Service\Filler
     */
    protected $filler;

    /**
     * Search
     *
     * @var \AnimeDb\Bundle\AniDbFillerBundle\Service\Search
     */
    protected $search;

    /**
     * Construct
     *
     * @param \AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser $browser
     * @param \AnimeDb\Bundle\AniDbFillerBundle\Service\Filler $filler
     * @param \AnimeDb\Bundle\AniDbFillerBundle\Service\Search $search
     */
    public function __construct(Browser $browser, Filler $filler, Search $search)
    {
        $this->browser = $browser;
        $this->filler = $filler;
        $this->search = $search;
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
     * Is can refill item from source
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return boolean
     */
    public function isCanRefill(Item $item, $field)
    {
        if (!in_array($field, $this->supported_fields)) {
            return false;
        }
        /* @var $source \AnimeDb\Bundle\CatalogBundle\Entity\Source */
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), $this->browser->getHost()) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Refill item field from source
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function refill(Item $item, $field)
    {
        // get source url
        $url = '';
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), $this->browser->getHost()) === 0) {
                $url = $source->getUrl();
                break;
            }
        }
        if (!$url || !preg_match(Filler::REG_ITEM_ID, $url, $match)) {
            return $item;
        }

        // get data
        $body = $this->browser->get('anime', ['aid' => $match['id']]);

        switch ($field) {
            case self::FIELD_DATE_END:
                $item->setDateEnd(new \DateTime($body->filter('enddate')->text()));
                break;
            case self::FIELD_DATE_PREMIERE:
                $item->setDatePremiere(new \DateTime($body->filter('startdate')->text()));
                break;
            case self::FIELD_EPISODES:
                $this->filler->setEpisodes($item, $body);
                break;
            case self::FIELD_EPISODES_NUMBER:
                $item->setEpisodesNumber($body->filter('episodecount')->text());
                break;
            case self::FIELD_GENRES:
                $new_item = $this->filler->setGenres(new Item(), $body);
                /* @var $new_genre \AnimeDb\Bundle\CatalogBundle\Entity\Genre */
                foreach ($new_item->getGenres() as $new_genre) {
                    // check of the existence of the genre
                    /* @var $genre \AnimeDb\Bundle\CatalogBundle\Entity\Genre */
                    foreach ($item->getGenres() as $genre) {
                        if ($new_genre->getId() == $genre->getId()) {
                            continue 2;
                        }
                    }
                    $item->addGenre($new_genre);
                }
                break;
            case self::FIELD_NAMES:
                $new_item = $this->filler->setNames(new Item(), $body);
                // set main name in top of names list
                $names = array_merge([(new Name)->setName($new_item->getName())], $new_item->getNames()->toArray());
                /* @var $new_name \AnimeDb\Bundle\CatalogBundle\Entity\Name */
                foreach ($names as $new_name) {
                    // check of the existence of the name
                    /* @var $name \AnimeDb\Bundle\CatalogBundle\Entity\Name */
                    foreach ($item->getNames() as $name) {
                        if ($new_name->getName() == $name->getName()) {
                            continue 2;
                        }
                    }
                    $item->addName($new_name);
                }
                break;
            case self::FIELD_SOURCES:
                if ($url = $body->filter('url')->text()) {
                    $is_set = false;
                    // check of the existence of the source
                    /* @var $source \AnimeDb\Bundle\CatalogBundle\Entity\Source */
                    foreach ($item->getSources() as $source) {
                        if ($url == $source->getUrl()) {
                            $is_set = true;
                            break;
                        }
                    }
                    if (!$is_set) {
                        $source = new Source();
                        $source->setUrl($body->filter('url')->text());
                        $item->addSource($source);
                    }
                }
                break;
            case self::FIELD_SUMMARY:
                $reg = '#'.preg_quote($this->browser->getHost()).'/ch\d+ \[([^\]]+)\]#';
                $item->setSummary(preg_replace($reg, '$1', $body->filter('description')->text()));
                break;
        }

        return $item;
    }

    /**
     * Is can search
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return boolean
     */
    public function isCanSearch(Item $item, $field)
    {
        if (!in_array($field, $this->supported_fields)) {
            return false;
        }
        if ($this->isCanRefill($item, $field) || $item->getName()) {
            return true;
        }
        /* @var $name \AnimeDb\Bundle\CatalogBundle\Entity\Name */
        foreach ($item->getNames() as $name) {
            if ($name->getName()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Search items for refill
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     *
     * @return array [\AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\Item]
     */
    public function search(Item $item, $field)
    {
        // search source url
        $url = '';
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), $this->browser->getHost()) === 0) {
                $url = $source->getUrl();
                break;
            }
        }
        // can refill from source. not need search
        if ($url) {
            return [
                new ItemRefiller(
                    $item->getName(),
                    ['url' => $url],
                    $url,
                    $item->getCover(),
                    $item->getSummary()
                )
            ];
        }

        // get name for search
        if (!($name = $item->getName())) {
            foreach ($item->getNames() as $name) {
                if ($name) {
                    break;
                }
            }
        }

        $result = [];
        // do search
        if ($name) {
            $result = $this->search->search(['name' => $name]);
            /* @var $item \AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item */
            foreach ($result as $key => $item) {
                if ($query = parse_url($item->getLink(), PHP_URL_QUERY)) {
                    parse_str($query, $query);
                    $link = array_values($query)[0]['url'];
                    $result[$key] = new ItemRefiller(
                        $item->getName(),
                        ['url' => $link],
                        $link,
                        $item->getImage(),
                        $item->getDescription()
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Refill item field from search result
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param string $field
     * @param array $data
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function refillFromSearchResult(Item $item, $field, array $data)
    {
        if (!empty($data['url'])) {
            $source = new Source();
            $source->setUrl($data['url']);
            $item->addSource($source);
            $item = $this->refill($item, $field);
        }
        return $item;
    }
}