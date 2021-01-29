<?php

declare(strict_types=1);

namespace Aquis\XporterBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AppFixtures.
 *
 * @copyright Aquis Grana impex srl (http://www.webnou.ro/)
 * @author    Petronel Malutan <malutanpetronel@gmail.com>
 */
class AppFixtures extends Fixture implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * AppFixtures constructor.
     */
    public function __construct()
    {
    }

    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     *
     * @return array|object[]
     */
    public function load(ObjectManager $manager)
    {
        /* @var $manager ObjectManager */

        $fixturesFiles = [
            __DIR__.'/data.yml',
        ];

        $loader = $this->container->get('fidry_alice_data_fixtures.loader.doctrine');

        return $loader->load($fixturesFiles);
    }
}
