<?php
/**
 * Postmark integration
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@sumoheavy.com so we can send you a copy immediately.
 *
 * @category    SUMOHeavy
 * @package     SUMOHeavy_Postmark
 * @copyright   Copyright (c) SUMO Heavy Industries, LLC
 * @notice      The Postmark logo and name are trademarks of Wildbit, LLC
 * @license     http://www.opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
 */
namespace SUMOHeavy\Postmark\Model;

use \Zend\Mail;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class TransportTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $helper;

    /**
     * @var \SUMOHeavy\Postmark\Model\Transport
     */
    private $transport;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $transportPostmarkMock;

    private $message;

    public function setUp()
    {
        $this->objectManager = new ObjectManager($this);

        $this->className = Transport::class;

        $arguments = $this->getConstructorArguments();

        $this->transport = $this
            ->getMockBuilder($this->className)
            ->setConstructorArgs($arguments)
            ->setMethods(['sendMessage'])
            ->getMock();

        $this->originalSubject = $this
            ->objectManager
            ->getObject($this->className, $arguments);
    }

    protected function getConstructorArguments()
    {
        $arguments = $this->objectManager->getConstructArguments($this->className);
        $this->helper = $this->getMockBuilder(\SUMOHeavy\Postmark\Helper\Data::class)
            ->setMethods(['canUse'])
            ->disableOriginalConstructor()
            ->getMock();
        $arguments['helper'] = $this->helper;

        $this->message = $this->getMockBuilder(\Magento\Framework\Mail\Message::class)
            ->disableOriginalConstructor()
            ->setMethods(['getRawMessage'])
            ->getMock();
        $arguments['message'] = $this->message;

        $this->transportPostmarkMock = $this->getMockBuilder(\SUMOHeavy\Postmark\Model\Transport\Postmark::class)
            ->setMethods(['send'])
            ->disableOriginalConstructor()
            ->setConstructorArgs(['helper' => $this->helper])
            ->getMock();
        $arguments['transportPostmarkMock'] = $this->transportPostmarkMock;

        return $arguments;
    }

    public function testSendMessage()
    {
        $message = new Mail\Message;
        $message->setFrom('yash@stadiumgoods.com');
        $message->addTo('yash+1@stadiumgoods.com');

        $this->helper->expects($this->any())
            ->method('canUse')
            ->will($this->returnValue(true));

        $this->message->expects($this->any())
            ->method('getRawMessage')
            ->willReturn($message->toString());

        $this->transportPostmarkMock->expects($this->any())
            ->method('send')
            ->with(Mail\Message::fromString($this->message->getRawMessage()))
            ->will($this->returnValue(null));

        $return = $this->transport->expects($this->any())
            ->method('sendMessage')
            ->will($this->returnValue(null));

        $this->assertEquals(null, $this->transport->sendMessage());
    }

    public function testSendMessageException()
    {
        $message = new Mail\Message;
        $message->setFrom('yash@stadiumgoods.com');
        $message->addTo('yash+1@stadiumgoods.com');

        $this->helper->expects($this->any())
            ->method('canUse')
            ->will($this->returnValue(true));

        $this->message->expects($this->any())
            ->method('getRawMessage')
            ->willReturn($message->toString());

        $this->transportPostmarkMock->expects($this->any())
            ->method('send')
            ->will($this->throwException(new \SUMOHeavy\Postmark\Model\Transport\Exception('test')));

        $this->transport->expects($this->any())
            ->method('sendMessage')
            ->will($this->throwException(new \SUMOHeavy\Postmark\Model\Transport\Exception('test')));
            
        try {
            $this->transport->sendMessage();
            $this->fail('Exception not thrown');
        } catch(\Exception $e) {
            $this->assertEquals('test', $e->getMessage());
        }
    }
}
