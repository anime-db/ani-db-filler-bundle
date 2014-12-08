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
use AnimeDb\Bundle\CatalogBundle\Event\Storage\AddNewItem;
use AnimeDb\Bundle\CatalogBundle\Event\Storage\StoreEvents;
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
     */
    public function __construct(EventDispatcherInterface $dispatcher, RefillerService $refiller, Filler $filler)
    {
        $this->dispatcher = $dispatcher;
        $this->refiller = $refiller;
        $this->filler = $filler;
    }

    /**
     * On add new item
     *
     * @param \AnimeDb\Bundle\CatalogBundle\Event\Storage\AddNewItem $event
     */
    public function onAddNewItem(AddNewItem $event)
    {
        $item = $event->getItem();
        if (!$event->getFillers()->contains($this->filler) && ($url = $this->refiller->getSourceForFill($item))) {
            try {
                $new_item = $this->filler->fill(['url' => $url]);
            } catch (\Exception $e) {
                return;
            }

            // fill item
            if (!$item->getDateEnd()) {
                $item->setDateEnd($new_item->getDateEnd());
            }
            if (!$item->getDatePremiere()) {
                $item->setDatePremiere($new_item->getDatePremiere());
            }
            if (!$item->getEpisodes()) {
                $item->setEpisodes($new_item->getEpisodes());
            }
            if (!$item->getEpisodesNumber()) {
                $item->setEpisodesNumber($new_item->getEpisodesNumber());
            }
            if (!$item->getSummary()) {
                $item->setSummary($new_item->getSummary());
            }
            if (!$item->getType()) {
                $item->setType($new_item->getType());
            }
            if (!$item->getCover()) {
                $item->setCover($new_item->getCover());
            }
            foreach ($new_item->getGenres() as $new_genre) {
                $item->addGenre($new_genre);
            }
            // set main name in top of names list
            $new_names = $new_item->getNames()->toArray();
            array_unshift($new_names, (new Name())->setName($new_item->getName()));
            foreach ($new_names as $new_name) {
                $item->addName($new_name);
            }
            foreach ($new_item->getSources() as $new_source) {
                $item->addSource($new_source);
            }

            $event->addFiller($this->filler);
            // resend event
            $this->dispatcher->dispatch(StoreEvents::ADD_NEW_ITEM, clone $event);
            $event->stopPropagation();
        }
    }
}
