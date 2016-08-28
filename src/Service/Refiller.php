<?php
/**
 * AnimeDb package.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */
namespace AnimeDb\Bundle\AniDbFillerBundle\Service;

use AnimeDb\Bundle\CatalogBundle\Entity\Genre;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\RefillerInterface;
use AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Refiller\Item as ItemRefiller;
use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Search\Item as ItemSearch;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;

class Refiller implements RefillerInterface
{
    /**
     * @var string
     */
    const NAME = 'anidb';

    /**
     * @var string
     */
    const TITLE = 'AniDB.net';

    /**
     * List of supported fields.
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
        self::FIELD_SUMMARY,
    ];

    /**
     * @var Browser
     */
    private $browser;

    /**
     * @var Filler
     */
    protected $filler;

    /**
     * @var Search
     */
    protected $search;

    /**
     * @param Browser $browser
     * @param Filler $filler
     * @param Search $search
     */
    public function __construct(Browser $browser, Filler $filler, Search $search)
    {
        $this->browser = $browser;
        $this->filler = $filler;
        $this->search = $search;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return self::NAME;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return self::TITLE;
    }

    /**
     * Is can refill item from source.
     *
     * @param Item $item
     * @param string $field
     *
     * @return bool
     */
    public function isCanRefill(Item $item, $field)
    {
        return in_array($field, $this->supported_fields) && $this->getSourceForFill($item);
    }

    /**
     * Refill item field from source.
     *
     * @param Item $item
     * @param string $field
     *
     * @return Item
     */
    public function refill(Item $item, $field)
    {
        $url = $this->getSourceForFill($item);
        if (!$url || !preg_match(Filler::REG_ITEM_ID, $url, $match)) {
            return $item;
        }

        // get data
        $body = $this->browser->getCrawler('anime', ['aid' => $match['id']]);

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
                /* @var $new_genre Genre */
                foreach ($new_item->getGenres() as $new_genre) {
                    $item->addGenre($new_genre);
                }
                break;
            case self::FIELD_NAMES:
                $new_item = $this->filler->setNames(new Item(), $body);
                // set main name in top of names list
                $new_names = $new_item->getNames()->toArray();
                array_unshift($new_names, (new Name())->setName($new_item->getName()));
                foreach ($new_names as $new_name) {
                    $item->addName($new_name);
                }
                break;
            case self::FIELD_SOURCES:
                if ($url = $body->filter('url')->text()) {
                    $item->addSource((new Source())->setUrl($url));
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
     * @param Item $item
     * @param string $field
     *
     * @return bool
     */
    public function isCanSearch(Item $item, $field)
    {
        if (!in_array($field, $this->supported_fields)) {
            return false;
        }
        if ($this->isCanRefill($item, $field) || $item->getName()) {
            return true;
        }
        /* @var $name Name */
        foreach ($item->getNames() as $name) {
            if ($name->getName()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Search items for refill.
     *
     * @param Item $item
     * @param string $field
     *
     * @return ItemRefiller[]
     */
    public function search(Item $item, $field)
    {
        $url = $this->getSourceForFill($item);
        // can refill from source. not need search
        if ($url) {
            return [
                new ItemRefiller(
                    $item->getName(),
                    ['url' => $url],
                    $url,
                    $item->getCover(),
                    $item->getSummary()
                ),
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
            /* @var $item ItemSearch */
            foreach ($result as $key => $item) {
                // get real url from search result
                if ($query = parse_url($item->getLink(), PHP_URL_QUERY)) {
                    parse_str($query, $query);
                    $query = array_values($query);
                    if (!empty($query[0]['url'])) {
                        $result[$key] = new ItemRefiller(
                            $item->getName(),
                            ['url' => $query[0]['url']],
                            $query[0]['url'],
                            $item->getImage(),
                            $item->getDescription()
                        );
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Refill item field from search result.
     *
     * @param Item $item
     * @param string $field
     * @param array $data
     *
     * @return Item
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

    /**
     * @param Item $item
     *
     * @return string
     */
    public function getSourceForFill(Item $item)
    {
        /* @var $source Source */
        foreach ($item->getSources() as $source) {
            if (strpos($source->getUrl(), $this->browser->getHost()) === 0) {
                return $source->getUrl();
            }
        }

        return '';
    }
}
