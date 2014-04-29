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
use Symfony\Component\Validator\Validator;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;
use AnimeDb\Bundle\CatalogBundle\Entity\Source;
use AnimeDb\Bundle\CatalogBundle\Entity\Type;
use AnimeDb\Bundle\CatalogBundle\Entity\Genre;
use AnimeDb\Bundle\CatalogBundle\Entity\Studio;
use AnimeDb\Bundle\CatalogBundle\Entity\Image;
use AnimeDb\Bundle\AppBundle\Entity\Field\Image as ImageField;
use AnimeDb\Bundle\AniDbFillerBundle\Form\Filler as FillerForm;
use Symfony\Component\DomCrawler\Crawler;

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
     * Validator
     *
     * @var \Symfony\Component\Validator\Validator
     */
    private $validator;

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
     * @param \Doctrine\Bundle\DoctrineBundle\Registry $doctrine
     * @param \Symfony\Component\Validator\Validator $validator
     * @param string $locale
     */
    public function __construct(
        Browser $browser,
        Registry $doctrine,
        Validator $validator,
        $locale
    ) {
        $this->browser = $browser;
        $this->doctrine = $doctrine;
        $this->validator = $validator;
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
     * @return \AnimeDb\Bundle\ShikimoriFillerBundle\Form\Filler
     */
    public function getForm()
    {
        return new FillerForm($this->browser->getHost());
    }

    /**
     * Fill item from source
     *
     * @param array $date
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
        $this->setCover($item, $body);
        $this->setNames($item, $body);
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
        /* @ var $title \DOMElement */
        foreach ($titles as $title) {
            $lang = substr($title->attributes->item(0)->nodeValue, 0, 2);
            if ($lang != 'x-') {
                $title = new Crawler($title);
                $names[$lang][$title->attr('type')] = $title->text();
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
     *
     * @return \AnimeDb\Bundle\CatalogBundle\Entity\Item
     */
    public function setCover(Item $item, Crawler $body)
    {
        if ($image = $body->filter('picture')->text()) {
            try {
                $image = $this->browser->getImageUrl($image);
                $ext = pathinfo(parse_url($image, PHP_URL_PATH), PATHINFO_EXTENSION);
                $target = self::NAME.'/'.$body->filterXPath('//@id')->text().'/cover.'.$ext;
                $item->setCover($this->uploadImage($image, $target));
            } catch (\Exception $e) {}
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
    protected function uploadImage($url, $target = null) {
        $image = new ImageField();
        $image->setRemote($url);
        $image->upload($this->validator, $target);
        return $image->getPath();
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