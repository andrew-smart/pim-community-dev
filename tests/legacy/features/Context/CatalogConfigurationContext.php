<?php

namespace Context;

use Akeneo\Test\Integration\Configuration;
use Akeneo\Test\IntegrationTestsBundle\Loader\FixturesLoader;
use Akeneo\Tool\Component\StorageUtils\Cache\EntityManagerClearerInterface;
use Pim\Behat\Context\PimContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A context for initializing catalog configuration
 *
 * @author    Filips Alpe <filips@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class CatalogConfigurationContext extends PimContext
{
    /** @var string Catalog configuration path */
    protected $catalogPath = 'catalog';

    /** @var array Additional catalog configuration directories */
    protected $extraDirectories = [];

    /** @var FixturesLoader */
    protected $fixturesLoader;

    /** @var EntityManagerClearerInterface */
    protected $entityManagerClearer;

    public function __construct(
        string $mainContextClass,
        FixturesLoader $fixturesLoader,
        EntityManagerClearerInterface $entityManagerClearer
    ) {
        parent::__construct($mainContextClass);

        $this->fixturesLoader = $fixturesLoader;
        $this->entityManagerClearer = $entityManagerClearer;
    }

    /**
     * Add an additional directory for catalog configuration files
     *
     * @param string $directory
     *
     * @return CatalogConfigurationContext
     */
    public function addConfigurationDirectory($directory)
    {
        $this->extraDirectories[] = $directory;

        return $this;
    }

    /**
     * @param string $catalog
     *
     * @Given /^(?:a|an|the) "([^"]*)" catalog configuration$/
     */
    public function aCatalogConfiguration($catalog)
    {
        $this->fixturesLoader->load(new Configuration([__DIR__.'/'.$this->catalogPath . '/' . $catalog]));
        $this->entityManagerClearer->clear();
    }
}
