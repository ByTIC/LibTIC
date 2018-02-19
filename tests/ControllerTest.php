<?php

namespace Nip\Tests;

use Nip\Controller;

/**
 * Class ControllerTest.
 */
class ControllerTest extends AbstractTest
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    public function testDynamicCallHelper()
    {
        $controller = new Controller();

        static::assertInstanceOf('Nip_Helper_Url', $controller->Url());
        static::assertInstanceOf('Nip_Helper_Xml', $controller->Xml());
        static::assertInstanceOf('Nip_Helper_Passwords', $controller->Passwords());
    }

    public function testGetHelper()
    {
        $controller = new Controller();

        static::assertInstanceOf('Nip_Helper_Url', $controller->getHelper('Url'));
        static::assertInstanceOf('Nip_Helper_Xml', $controller->getHelper('Xml'));
        static::assertInstanceOf('Nip_Helper_Passwords', $controller->getHelper('passwords'));
    }
}