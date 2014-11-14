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

use AnimeDb\Bundle\CatalogBundle\Plugin\Fill\Filler\Filler as FillerPlugin;
use AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser;
use Doctrine\Bundle\DoctrineBundle\Registry;
use AnimeDb\Bundle\AppBundle\Service\Downloader;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Genre;
use AnimeDb\Bundle\AniDbFillerBundle\Form\Type\Filler as FillerForm;
use Symfony\Component\DomCrawler\Crawler;
use Knp\Menu\ItemInterface;

/**
 * Search from site AniDB.net
 * 
 * @link http://anidb.net/
 * @package AnimeDb\Bundle\AniDbFillerBundle\Service
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Filler extends FillerPlugin
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
     * RegExp for get item id
     *
     * @var string
     */
    const REG_ITEM_ID = '#/perl\-bin/animedb\.pl\?show=anime&aid=(?<id>\d+)#';

    /**
     * Browser
     *
     * @var \AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser
     */
    private $browser;

    /**
     * Doctrine
     *
     * @var \Doctrine\Bundle\DoctrineBundle\Registry
     */
    private $doctrine;

    /**
     * Downloader
     *
     * @var \AnimeDb\Bundle\AppBundle\Service\Downloader
     */
    private $downloader;

    /**
     * Locale
     *
     * @var string
     */
    protected $locale;

    /**
     * AniDB category to genre
     *
     * <code>
     *     { from: to, ... }
     * </code>
     *
     * @var array
     */
    protected $category_to_genre = [
        'Alternative History' => 'History',
        'Anti-War' => 'War',
        'Badminton' => 'Sport',
        'Bakumatsu - Meiji Period' => 'History',
        'Band' => 'Music',
        'Baseball' => 'Sport',
        'Basketball' => 'Sport',
        'Battle Royale' => 'War',
        'Board Games' => 'Game',
        'Boxing' => 'Sport',
        'Catholic School' => 'School',
        'Chess' => 'Sport',
        'Clubs' => 'School',
        'College' => 'School',
        'Combat' => 'Action',
        'Conspiracy' => 'Thriller',
        'Contemporary Fantasy' => 'Fantasy',
        'Cops' => 'Police',
        'Daily Life' => 'Slice of life',
        'Dark Elf' => 'Fantasy',
        'Dark Fantasy' => 'Fantasy',
        'Dodgeball' => 'Sport',
        'Dragon' => 'Fantasy',
        'Edo Period' => 'Fantasy',
        'Elementary School' => 'School',
        'Elf' => 'Fantasy',
        'Fairies' => 'Fantasy',
        'Fantasy World' => 'Fantasy',
        'Feudal Warfare' => 'War',
        'Football' => 'Sport',
        'Formula Racing' => 'Sport',
        'Ghost' => 'Supernatural',
        'Go' => 'Game',
        'Golf' => 'Sport',
        'Gunfights' => 'War',
        'Gymnastics' => 'Sport',
        'Heian Period' => 'History',
        'High Fantasy' => 'Fantasy',
        'High School' => 'School',
        'Historical' => 'History',
        'Ice Skating' => 'Sport',
        'Inline Skating' => 'Sport',
        'Jousting' => 'Sport',
        'Judo' => 'Sport',
        'Kendo' => 'Sport',
        'Law and Order' => 'Police',
        'Magic Circles' => 'Magic',
        'Mahjong' => 'Game',
        'Mahou Shoujo' => 'Mahoe shoujo',
        'Martial Arts' => 'Martial arts',
        'Military' => 'War',
        'Motorsport' => 'Sport',
        'Muay Thai' => 'Sport',
        'Ninja' => 'Samurai',
        'Pirate' => 'Adventure',
        'Post-apocalypse' => 'Apocalyptic fiction',
        'Post-War' => 'War',
        'Proxy Battles' => 'War',
        'Reverse Harem' => 'Harem',
        'Rugby' => 'Sport',
        'School Dormitory' => 'School',
        'School Excursion' => 'School',
        'School Festival' => 'School',
        'School Life' => 'School',
        'School Sports Festival' => 'School',
        'Sci-Fi' => 'Sci-fi',
        'Sengoku Period' => 'History',
        'Shougi' => 'Game',
        'Shoujo Ai' => 'Shoujo-ai',
        'Shounen Ai' => 'Shounen-ai',
        'Spellcasting' => 'Magic',
        'Sports' => 'Sport',
        'Street Racing' => 'Cars',
        'Swimming' => 'Sport',
        'Swordplay' => 'Sport',
        'Tennis' => 'Sport',
        'Victorian Period' => 'History',
        'Volleyball' => 'Sport',
        'Witch' => 'Magic',
        'World War I' => 'War',
        'World War II' => 'War',
        'Wrestling' => 'Action',
    ];

    /**
     * Construct
     *
     * @param \AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser $browser
     * @param \Doctrine\Bundle\DoctrineBundle\Registry $doctrine
     * @param \AnimeDb\Bundle\AppBundle\Service\Downloader $downloader
     * @param string $locale
     */
    public function __construct(
        Browser $browser,
        Registry $doctrine,
        Downloader $downloader,
        $locale
    ) {
        $this->browser = $browser;
        $this->doctrine = $doctrine;
        $this->downloader = $downloader;
        $this->locale = $locale;
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
     * Get form
     *
     * @return \AnimeDb\Bundle\AniDbFillerBundle\Form\Type\Filler
     */
    public function getForm()
    {
        return new FillerForm($this->browser->getHost());
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
     * Fill item from source
     *
     * @param array $data
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item|null
     */
    public function fill(array $data)
    {
        if (empty($data['url']) || !is_string($data['url']) ||
            strpos($data['url'], $this->browser->getHost()) !== 0 ||
            !preg_match(self::REG_ITEM_ID, $data['url'], $match)
        ) {
            return null;
        }
        $body = $this->browser->get('anime', ['aid' => $match['id']]);

        $item = new Item();
        $item->setEpisodesNumber($body->filter('episodecount')->text());
        $item->setDatePremiere(new \DateTime($body->filter('startdate')->text()));
        $item->setDateEnd(new \DateTime($body->filter('enddate')->text()));
        // remove links in summary
        $reg = '#'.preg_quote($this->browser->getHost()).'/ch\d+ \[([^\]]+)\]#';
        $item->setSummary(preg_replace($reg, '$1', $body->filter('description')->text()));

        // set main source
        $source = new Source();
        $source->setUrl($data['url']);
        $item->addSource($source);

        // add url to offsite
        $source = new Source();
        $source->setUrl($body->filter('url')->text());
        $item->addSource($source);

        // set complex data
        $this->setCover($item, $body, $match['id']);
        $this->setNames($item, $body);
        $this->setEpisodes($item, $body);
        $this->setType($item, $body);
        $this->setGenres($item, $body);
        return $item;
    }

    /**
     * Set item names
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \Symfony\Component\DomCrawler\Crawler $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function setNames(Item $item, Crawler $body)
    {
        $titles = $body->filter('titles > title');
        $names = [];
        /* @var $title \DOMElement */
        foreach ($titles as $title) {
            $lang = substr($title->attributes->item(0)->nodeValue, 0, 2);
            if ($lang != 'x-') {
                $names[$lang][$title->getAttribute('type')] = $title->nodeValue;
            }
        }

        // set main name
        if (!empty($names[$this->locale])) {
            $item->setName($this->getNameForLocale($this->locale, $names));
        } elseif ($this->locale != 'en' && !empty($names['en'])) {
            $item->setName($this->getNameForLocale('en', $names));
        } else {
            $item->setName($this->getNameForLocale(array_keys($names)[0], $names));
        }

        // set other names
        $other = [];
        foreach ($names as $locales) {
            foreach ($locales as $name) {
                $other[] = $name;
            }
        }
        $other = array_unique($other);
        sort($other);

        foreach ($other as $name) {
            $item->addName((new Name())->setName($name));
        }

        return $item;
    }

    /**
     * Set item cover
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \Symfony\Component\DomCrawler\Crawler $body
     * @param integer $id
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function setCover(Item $item, Crawler $body, $id)
    {
        if ($image = $body->filter('picture')->text()) {
            try {
                $image = $this->browser->getImageUrl($image);
                if ($path = parse_url($image, PHP_URL_PATH)) {
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    $item->setCover($this->uploadImage($image, self::NAME.'/'.$id.'/cover.'.$ext));
                }
            } catch (\Exception $e) {} // error while retrieving images is not critical
        }
        return $item;
    }

    /**
     * Set item episodes
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \Symfony\Component\DomCrawler\Crawler $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function setEpisodes(Item $item, Crawler $body)
    {
        $episodes = '';
        foreach ($body->filter('episodes > episode') as $episode) {
            $episode = new Crawler($episode);
            $episodes .= $episode->filter('epno')->text().'. '.$this->getEpisodeTitle($episode)."\n";
        }
        $item->setEpisodes(trim($episodes));
        return $item;
    }

    /**
     * Get episode title
     *
     * @param \Symfony\Component\DomCrawler\Crawler $episode
     *
     * @return string
     */
    protected function getEpisodeTitle(Crawler $episode)
    {
        $titles = [];
        /* @var $title \DOMElement */
        foreach ($episode->filter('title') as $title) {
            $lang = substr($title->attributes->item(0)->nodeValue, 0, 2);
            if ($lang == $this->locale) {
                return $title->nodeValue;
            }
            if ($lang != 'x-') {
                $titles[$lang] = $title->nodeValue;
            }
        }

        // get EN lang or first
        if (!empty($titles['en'])) {
            return $titles['en'];
        } else {
            return array_shift($titles);
        }
    }

    /**
     * Set item type
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \Symfony\Component\DomCrawler\Crawler $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function setType(Item $item, Crawler $body)
    {
        $rename = [
            'TV Series' => 'TV',
            'Movie' => 'Feature',
            'Web' => 'ONA',
        ];
        $type = $body->filter('anime > type')->text();
        $type = isset($rename[$type]) ? $rename[$type] : $type;
        return $item->setType($this->doctrine->getRepository('AnimeDbCatalogBundle:Type')->findOneBy(['name' => $type]));
    }

    /**
     * Set item genres
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Entity\Item $item
     * @param \Symfony\Component\DomCrawler\Crawler $body
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function setGenres(Item $item, Crawler $body)
    {
        $repository = $this->doctrine->getRepository('AnimeDbCatalogBundle:Genre');
        $categories = $body->filter('categories > category > name');
        foreach ($categories as $category) {
            if (isset($this->category_to_genre[$category->nodeValue])) {
                $genre = $repository->findOne(['name' => $this->category_to_genre[$category->nodeValue]]);
            } else {
                $genre = $repository->findOne(['name' => $category->nodeValue]);
            }
            if ($genre instanceof Genre) {
                $item->addGenre($genre);
            }
        }
        return $item;
    }

    /**
     * Upload image from url
     *
     * @param string $url
     * @param string|null $target
     *
     * @return string
     */
    protected function uploadImage($url, $target) {
        $this->downloader->image($url, $target);
        return $target;
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
        if (isset($names[$locale]['main'])) {
            $name = $names[$locale]['main'];
            unset($names[$locale]['main']);
        } elseif (isset($names[$locale]['official'])) {
            $name = $names[$locale]['official'];
            unset($names[$locale]['official']);
        } else {
            $name = array_shift($names[$locale]);
        }
        return $name;
    }
}
