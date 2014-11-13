<?php
/**
 * AnimeDb package
 *
 * @package   AnimeDb
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/GPL-3.0 GPL v3
 */

namespace AnimeDb\Bundle\AniDbFillerBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Get item from filler
 *
 * @package AnimeDb\Bundle\AniDbFillerBundle\Form
 * @author  Peter Gribanov <info@peter-gribanov.ru>
 */
class Filler extends AbstractType
{
    /**
     * HTTP host
     *
     * @var string
     */
    protected $host;

    /**
     * Construct
     *
     * @param string $host
     */
    public function __construct($host)
    {
        $this->host = $host;
    }

    /**
     * (non-PHPdoc)
     * @see \Symfony\Component\Form\AbstractType::buildForm()
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->setMethod('GET')
            ->add('url', 'text', [
                'label' => 'URL address',
                'attr' => [
                    'placeholder' => $this->host.'/',
                ],
            ]);
    }

    /**
     * (non-PHPdoc)
     * @see \Symfony\Component\Form\FormTypeInterface::getName()
     */
    public function getName()
    {
        return 'animedb_anidbfillerbundle_filler';
    }
}
