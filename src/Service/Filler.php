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
use AnimeDb\Bundle\AppBundle\Service\Downloader\Entity\EntityInterface;

class Filler extends FillerPlugin
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
     * RegExp for get item id
     *
     * @var string
     */
    const REG_ITEM_ID = '#/perl\-bin/animedb\.pl\?show=anime&aid=(?<id>\d+)#';

    /**
     * @var Browser
     */
    private $browser;

    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @var Downloader
     */
    private $downloader;

    /**
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
     * @param Browser $browser
     * @param Registry $doctrine
     * @param Downloader $downloader
     * @param $locale
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
     * @return string
     */
    public function getName() {
        return self::NAME;
    }

    /**
     * @return string
     */
    public function getTitle() {
        return self::TITLE;
    }

    /**
     * @return FillerForm
     */
    public function getForm()
    {
        return new FillerForm($this->browser->getHost());
    }

    /**
     * Build menu for plugin
     *
     * @param ItemInterface $item
     *
     * @return ItemInterface
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
     * @return Item|null
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
     * @param Item $item
     * @param Crawler $body
     *
     * @return Item
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
     * @param Item $item
     * @param Crawler $body
     * @param string $id
     *
     * @return Item
     */
    public function setCover(Item $item, Crawler $body, $id)
    {
        if ($image = $body->filter('picture')->text()) {
            try {
                $image = $this->browser->getImageUrl($image);
                if ($path = parse_url($image, PHP_URL_PATH)) {
                    $ext = pathinfo($path, PATHINFO_EXTENSION);
                    $item->setCover(self::NAME.'/'.$id.'/cover.'.$ext);
                    $this->uploadImageFromUrl($image, $item);
                }
            } catch (\Exception $e) {
                // error while retrieving images is not critical
            }
        }
        return $item;
    }

    /**
     * @param Item $item
     * @param Crawler $body
     *
     * @return Item
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
     * @param Crawler $episode
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
     * @param Item $item
     * @param Crawler $body
     *
     * @return Item
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
        return $item->setType(
            $this
                ->doctrine
                ->getRepository('AnimeDbCatalogBundle:Type')
                ->findOneBy(['name' => $type])
        );
    }

    /**
     * @param Item $item
     * @param Crawler $body
     *
     * @return Item
     */
    public function setGenres(Item $item, Crawler $body)
    {
        $repository = $this->doctrine->getRepository('AnimeDbCatalogBundle:Genre');
        $categories = $body->filter('categories > category > name');
        foreach ($categories as $category) {
            if (isset($this->category_to_genre[$category->nodeValue])) {
                $genre = $repository->findOneBy(['name' => $this->category_to_genre[$category->nodeValue]]);
            } else {
                $genre = $repository->findOneBy(['name' => $category->nodeValue]);
            }
            if ($genre instanceof Genre) {
                $item->addGenre($genre);
            }
        }
        return $item;
    }

    /**
     * @param string $url
     * @param EntityInterface $entity
     *
     * @return boolean
     */
    protected function uploadImageFromUrl($url, EntityInterface $entity) {
        return $this->downloader->image($url, $this->downloader->getRoot().$entity->getWebPath());
    }

    /**
     * @param string $locale
     * @param array $names
     *
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

    /**
     * @param string $url
     *
     * @return boolean
     */
    public function isSupportedUrl($url)
    {
        return strpos($url, $this->browser->getHost()) === 0;
    }
}
