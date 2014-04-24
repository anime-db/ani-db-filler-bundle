<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\AniDbFillerBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * AniDB item title
 *
 * @ORM\Entity
 * @ORM\Table(
 *     name="ani_db_title",
 *     indexes={
 *         @ORM\Index(name="ani_db_title_aid_idx", columns={"aid"}),
 *         @ORM\Index(name="ani_db_title_title_idx", columns={"title"})
 *     }
 * )
 * @IgnoreAnnotation("ORM")
 *
 * @package AnimeDb\Bundle\AniDbFillerBundle\Entity
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Title
{
    /**
     * Primary title
     *
     * @var integer
     */
    const TYPE_PRIMARY = 1;

    /**
     * Synonyms title
     *
     * @var integer
     */
    const TYPE_SYNONYMS = 2;

    /**
     * Short title
     *
     * @var integer
     */
    const TYPE_SHORT = 3;

    /**
     * Official title
     *
     * @var integer
     */
    const TYPE_OFFICIAL = 4;

    /**
     * Aid
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @Assert\NotBlank()
     *
     * @var integer
     */
    protected $aid;

    /**
     * Type
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @Assert\NotBlank()
     * @Assert\Choice(choices = {1, 2, 3, 4}, message = "Choose a valid type.")
     *
     * @var integer
     */
    protected $type;

    /**
     * Language
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=2)
     * @Assert\NotBlank()
     * @Assert\Locale()
     *
     * @var string
     */
    protected $language;

    /**
     * Item name
     *
     * @ORM\Id
     * @ORM\Column(type="string", length=256)
     * @Assert\NotBlank()
     *
     * @var string
     */
    protected $title;

    /**
     * Set aid
     *
     * @param integer $aid
     * @return \AnimeDb\Bundle\AniDbFillerBundle\Entity\Title
     */
    public function setAid($aid)
    {
        $this->aid = $aid;
        return $this;
    }

    /**
     * Get aid
     *
     * @return integer 
     */
    public function getAid()
    {
        return $this->aid;
    }

    /**
     * Set type
     *
     * @param integer $type
     * @return \AnimeDb\Bundle\AniDbFillerBundle\Entity\Title
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Get type
     *
     * @return integer 
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get available types
     *
     * @return array
     */
    public static function getAvailableTypes()
    {
        return [
            self::TYPE_OFFICIAL,
            self::TYPE_PRIMARY,
            self::TYPE_SHORT,
            self::TYPE_SYNONYMS
        ];
    }

    /**
     * Set language
     *
     * @param string $language
     * @return \AnimeDb\Bundle\AniDbFillerBundle\Entity\Title
     */
    public function setLanguage($language)
    {
        $this->language = $language;
        return $this;
    }

    /**
     * Get language
     *
     * @return string 
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Set title
     *
     * @param string $title
     * @return \AnimeDb\Bundle\AniDbFillerBundle\Entity\Title
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Get title
     *
     * @return string 
     */
    public function getTitle()
    {
        return $this->title;
    }
}