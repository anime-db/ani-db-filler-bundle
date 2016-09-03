<?php
/**
 * AnimeDb package.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */
namespace AnimeDb\Bundle\AniDbFillerBundle\Service;

use AnimeDb\Bundle\AniDbBrowserBundle\Service\Browser;

class SummaryCleaner
{
    /**
     * @var Browser
     */
    protected $browser;

    /**
     * @param Browser $browser
     */
    public function __construct(Browser $browser)
    {
        $this->browser = $browser;
    }

    /**
     * @param string $string
     *
     * @return string
     */
    public function clean($string)
    {
        $reg = '#'.preg_quote($this->browser->getHost()).'/ch\d+ \[([^\]]+)\]#';
        $string = preg_replace($reg, '$1', $string);
        $string = preg_replace('/\[[^\[\]]+\]/', '', $string); // remove BB tags

        return $string;
    }
}
