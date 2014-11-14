<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\AniDbFillerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Media
 *
 * @package AnimeDb\Bundle\AniDbFillerBundle\Controller
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class MediaController extends Controller
{
    /**
     * Cache lifetime 6 month
     *
     * @var ineteger
     */
    const CACHE_LIFETIME = 15552000;

    /**
     * Get cover from anidb.net item id
     *
     * @param string $id
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function coverAction($id, Request $request)
    {
        /* @var $body \Symfony\Component\DomCrawler\Crawler */
        $body = $this->get('anime_db.ani_db.browser')->get('anime', ['aid' => $id]);
        /* @var $response \Symfony\Component\HttpFoundation\Response */
        $response = $this->get('cache_time_keeper')->getResponse([], self::CACHE_LIFETIME)
            ->setEtag(sha1($body->html()));
        $response->headers->set('Content-Type', 'image/jpeg');

        // response was not modified for this request
        if ($response->isNotModified($request)) {
            return $response;
        }

        if ($image = $body->filter('picture')->text()) {
            $image = $this->get('anime_db.ani_db.browser')->getImageUrl($image);
            if (!($content = @file_get_contents($image, false))) {
                throw new \RuntimeException('Failed download image from anidb.net');
            }
            $response->setContent($content);
        } else {
            throw $this->createNotFoundException('Cover not found');
        }

        return $response;
    }
}
