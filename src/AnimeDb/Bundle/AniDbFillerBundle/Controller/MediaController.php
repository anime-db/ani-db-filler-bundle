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
use Symfony\Component\HttpFoundation\Response;

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
        $response = new Response();
        $response->headers->set('Content-Type', 'image/jpeg');
        // set lifetime
        $response->setMaxAge(self::CACHE_LIFETIME);
        $response->setSharedMaxAge(self::CACHE_LIFETIME);
        $response->setExpires((new \DateTime())->modify('+'.self::CACHE_LIFETIME.' seconds'));
        // caching
        if ($last_update = $this->container->getParameter('last_update')) {
            $response->setLastModified(new \DateTime($last_update));
        }

        /* @var $body \Symfony\Component\DomCrawler\Crawler */
        $body = $this->get('anime_db.ani_db.browser')->get('anime', ['aid' => $id]);

        $response->setEtag(sha1($body->html()));

        // response was not modified for this request
        if ($response->isNotModified($request)) {
            return $response;
        }

        if ($image = $body->filter('picture')->text()) {
            $image = $this->get('anime_db.ani_db.browser')->getImageUrl($image);
            // add app code in request
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: '.$this->container->getParameter('anime_db.ani_db.app_code')."\r\n"
                ]
            ]);
            if (!($content = @file_get_contents($image, false, $context))) {
                throw new \RuntimeException('Failed download image from anidb.net');
            }
            $response->setContent($content);
        } else {
            throw $this->createNotFoundException('Cover not found');
        }

        return $response;
    }
}