<?php
/**
 * AnimeDb package.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */
namespace AnimeDb\Bundle\AniDbFillerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Guzzle\Http\Client;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    /**
     * @var int
     */
    const CACHE_LIFETIME = 15552000;

    /**
     * Get cover from anidb.net item id.
     *
     * @param string $id
     * @param Request $request
     *
     * @return Response
     */
    public function coverAction($id, Request $request)
    {
        /* @var $body Crawler */
        $body = $this->get('anime_db.ani_db.browser')->get('anime', ['aid' => $id]);
        /* @var $response Response */
        $response = $this->get('cache_time_keeper')->getResponse([], self::CACHE_LIFETIME)
            ->setEtag(sha1($body->html()));
        $response->headers->set('Content-Type', 'image/jpeg');

        // response was not modified for this request
        if ($response->isNotModified($request)) {
            return $response;
        }

        if ($image = $body->filter('picture')->text()) {
            $image = $this->get('anime_db.ani_db.browser')->getImageUrl($image);
            $image_response = (new Client())->get($image)->send();
            if (!$image_response->isSuccessful()) {
                throw new \RuntimeException('Failed download image from anidb.net');
            }
            $response->setContent($image_response->getBody(true));
        } else {
            throw $this->createNotFoundException('Cover not found');
        }

        return $response;
    }
}
