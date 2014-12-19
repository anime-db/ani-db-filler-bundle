<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\AniDbFillerBundle\Event\Listener;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use AnimeDb\Bundle\AniDbFillerBundle\Service\Refiller as RefillerService;
use AnimeDb\Bundle\AniDbFillerBundle\Service\Filler;
use AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser;
use AnimeDb\Bundle\CatalogBundle\Event\Storage\AddNewItem;
use AnimeDb\Bundle\CatalogBundle\Event\Storage\StoreEvents;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;

/**
 * Refiller for new item
 *
 * @package AnimeDb\Bundle\AniDbFillerBundle\Event\Listener
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Refiller
{
    /**
     * Refiller
     *
     * @var \AnimeDb\Bundle\AniDbFillerBundle\Service\Refiller
     */
    protected $refiller;

    /**
     * Filler
     *
     * @var \AnimeDb\Bundle\AniDbFillerBundle\Service\Filler
     */
    protected $filler;

    /**
     * Browser
     *
     * @var \AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser
     */
    private $browser;

    /**
     * Dispatcher
     *
     * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * Construct
     *
     * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher
     * @param \AnimeDb\Bundle\AniDbFillerBundle\Service\Refiller $refiller
     * @param \AnimeDb\Bundle\AniDbFillerBundle\Service\Filler $filler
     * @param \AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser $browser
     */
    public function __construct(
        EventDispatcherInterface $dispatcher,
        RefillerService $refiller,
        Filler $filler,
        Browser $browser
    ) {
        $this->dispatcher = $dispatcher;
        $this->refiller = $refiller;
        $this->filler = $filler;
        $this->browser = $browser;
    }

    /**
     * On add new item
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Event\Storage\AddNewItem $event
     */
    public function onAddNewItem(AddNewItem $event)
    {
        $item = $event->getItem();
        if (!$event->getFillers()->contains($this->filler) &&
            ($url = $this->refiller->getSourceForFill($item)) &&
            preg_match(Filler::REG_ITEM_ID, $url, $match)
        ) {
            try {
                // get data
                $body = $this->browser->get('anime', ['aid' => $match['id']]);
            } catch (\Exception $e) {
                return;
            }

            // fill item
            if (!$item->getDateEnd()) {
                $item->setDateEnd(new \DateTime($body->filter('enddate')->text()));
            }
            if (!$item->getDatePremiere()) {
                $item->setDatePremiere(new \DateTime($body->filter('startdate')->text()));
            }
            if (!$item->getEpisodes()) {
                $this->filler->setEpisodes($item, $body);
            }
            if (!$item->getEpisodesNumber()) {
                $item->setEpisodesNumber($body->filter('episodecount')->text());
            }
            if (!$item->getSummary()) {
                $reg = '#'.preg_quote($this->browser->getHost()).'/ch\d+ \[([^\]]+)\]#';
                $item->setSummary(preg_replace($reg, '$1', $body->filter('description')->text()));
            }
            if (!$item->getType()) {
                $this->filler->setType($item, $body);
            }
            if (!$item->getCover()) {
                $this->filler->setCover($item, $body, $match['id']);
            }
            $this->filler->setGenres($item, $body);

            // copy main and other names
            $new_item = $this->filler->setNames(new Item(), $body);
            // set main name in top of names list
            $new_names = $new_item->getNames()->toArray();
            array_unshift($new_names, (new Name())->setName($new_item->getName()));
            /* @var $new_name \AnimeDb\Bundle\CatalogBundle\Entity\Name */
            foreach ($new_names as $new_name) {
                $item->addName($new_name->setItem(null));
            }

            $event->addFiller($this->filler);
            // resend event
            $this->dispatcher->dispatch(StoreEvents::ADD_NEW_ITEM, clone $event);
            $event->stopPropagation();
        }
    }
}