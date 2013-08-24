<?php

namespace Pim\Bundle\ProductBundle\Tests\Unit\Validator\Constraints;

use Pim\Bundle\ProductBundle\Validator\Constraints\ProductValueNotBlank;

/**
 * Test related class
 *
 * @author    Romain Monceau <romain@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ProductValueNotBlankTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ProductValueNotBlank
     */
    protected $constraint;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        parent::setUp();

        $this->constraint = new ProductValueNotBlank();
    }

    /**
     * Test constraint entity
     */
    public function testExtendsContraint()
    {
        $this->assertInstanceOf('Symfony\Component\Validator\Constraint', $this->constraint);
    }

    /**
     * Test constraint messages
     */
    public function testMessages()
    {
        $this->assertNotNull($this->constraint->messageNotBlank);
        $this->assertNotNull($this->constraint->messageNotNull);
    }
}
