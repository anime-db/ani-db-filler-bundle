<?php
/**
 * AnimeDb package.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */
namespace AnimeDb\Bundle\AniDbFillerBundle\Event\Listener;

use AnimeDb\Bundle\AniDbFillerBundle\Service\SummaryCleaner;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use AnimeDb\Bundle\AniDbFillerBundle\Service\Refiller as RefillerService;
use AnimeDb\Bundle\AniDbFillerBundle\Service\Filler;
use AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser;
use AnimeDb\Bundle\CatalogBundle\Event\Storage\AddNewItem;
use AnimeDb\Bundle\CatalogBundle\Event\Storage\StoreEvents;
use AnimeDb\Bundle\CatalogBundle\Entity\Item;
use AnimeDb\Bundle\CatalogBundle\Entity\Name;

/**
 * Refiller for new item.
 */
class Refiller
{
    /**
     * @var RefillerService
     */
    protected $refiller;

    /**
     * @var Filler
     */
    protected $filler;

    /**
     * @var Browser
     */
    protected $browser;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var SummaryCleaner
     */
    protected $cleaner;

    /**
     * @param EventDispatcherInterface $dispatcher
     * @param RefillerService $refiller
     * @param Filler $filler
     * @param Browser $browser
     * @param SummaryCleaner $cleaner
     */
    public function __construct(
        EventDispatcherInterface $dispatcher,
        RefillerService $refiller,
        Filler $filler,
        Browser $browser,
        SummaryCleaner $cleaner
    ) {
        $this->dispatcher = $dispatcher;
        $this->refiller = $refiller;
        $this->filler = $filler;
        $this->browser = $browser;
        $this->cleaner = $cleaner;
    }

    /**
     * @param AddNewItem $event
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
                $item->setSummary($this->cleaner->clean($body->filter('description')->text()));
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
            /* @var $new_name Name */
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
