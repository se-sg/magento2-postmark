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
namespace SUMOHeavy\Postmark\Model\Transport;

use \Zend\Mail;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class PostmarkTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Zend_Http_Client_Adapter_Interface
     */
    protected $adapter;

    /**
     * @var \SUMOHeavy\Postmark\Model\Transport\Postmark;
     */
    protected $transport;

    /**
     * @var \SUMOHeavy\Postmark\Helper\Data
     */
    protected $helper;

    public function setUp()
    {
        $this->adapter = new \Zend_Http_Client_Adapter_Test();

        $this->objectManager = new ObjectManager($this);

        $this->className = Postmark::class;

        $arguments = $this->getConstructorArguments();

        $this->transport = $this
            ->getMockBuilder($this->className)
            ->setConstructorArgs($arguments)
            ->setMethods()
            ->getMock();

        $this->originalSubject = $this
            ->objectManager
            ->getObject($this->className, $arguments);

        $this->transport->getHttpClient()->setAdapter($this->adapter);
    }

    protected function getConstructorArguments()
    {
        $arguments = $this->objectManager->getConstructArguments($this->className);

        $this->helper = $this->getMockBuilder(\SUMOHeavy\Postmark\Helper\Data::class)
            ->setMethods(['getApiKey'])
            ->disableOriginalConstructor()
            ->getMock();
        $arguments['helper'] = $this->helper;

        $this->helper->expects($this->any())
            ->method('getApiKey')
            ->will($this->returnValue('test-api-key'));

        return $arguments;
    }

    public function testSendMail()
    {
        $message = new Mail\Message;

        $this->adapter->setResponse(
            "HTTP/1.1 200 OK"        . "\r\n" .
            "Content-type: text/json" . "\r\n" .
                                       "\r\n" .
            '{"success": true}'
        );
        $message->setFrom('yash@stadiumgoods.com');
        $message->addTo('yash+1@stadiumgoods.com');
        $response = $this->transport->_sendMail($message);
        $this->assertNotEmpty($response);
        $this->assertTrue($response['success']);
    }

    public function testGetHttpClient()
    {
        $this->assertInstanceOf('\Zend_Http_Client', $this->transport->getHttpClient());
    }

    public function testGetFrom()
    {
        $message = new Mail\Message;

        $message->setFrom('yash+1@stadiumgoods.com');
        $this->assertEquals('yash+1@stadiumgoods.com', $this->transport->getFrom($message));
    }

    public function testGetTo()
    {
        $message = new Mail\Message;

        $message->addTo('yash+1@stadiumgoods.com');
        $this->assertEquals('yash+1@stadiumgoods.com', $this->transport->getTo($message));

        $message->addTo('yash+2@stadiumgoods.com');

        $expected = 'yash+1@stadiumgoods.com,yash+2@stadiumgoods.com';
        $this->assertEquals($expected, str_replace(array(' ', "\n", "\t", "\r"), '', $this->transport->getTo($message)));
    }

    public function testGetCc()
    {
        $message = new Mail\Message;

        $message->addCc('yash+1@stadiumgoods.com');
        $this->assertEquals('yash+1@stadiumgoods.com', $this->transport->getCc($message));

        $message->addCc('yash+2@stadiumgoods.com');

        $expected = 'yash+1@stadiumgoods.com,yash+2@stadiumgoods.com';
        $this->assertEquals($expected,  str_replace(array(' ', "\n", "\t", "\r"), '', $this->transport->getCc($message)));
    }

    public function testGetBcc()
    {
        $message = new Mail\Message;

        $message->addBcc('yash+1@stadiumgoods.com');
        $this->assertEquals('yash+1@stadiumgoods.com', $this->transport->getBcc($message));

        $message->addBcc('yash+2@stadiumgoods.com');

        $expected = 'yash+1@stadiumgoods.com,yash+2@stadiumgoods.com';
        $this->assertEquals($expected,  str_replace(array(' ', "\n", "\t", "\r"), '', $this->transport->getBcc($message)));
    }

    public function testGetReplyTo()
    {
        $message = new Mail\Message;

        $this->assertEmpty($this->transport->getReplyTo($message));

        $message->setReplyTo('yash+1@stadiumgoods.com');
        $this->assertEquals('yash+1@stadiumgoods.com', $this->transport->getReplyTo($message));
    }

    public function testGetSubject()
    {
        $message = new Mail\Message;

        $this->assertEmpty($this->transport->getSubject($message));

        $message->setSubject('test');
        $this->assertEquals('test', $this->transport->getSubject($message));
    }

    public function testGetBodyText()
    {
        $message = new Mail\Message;

        $this->assertEmpty($this->transport->getBodyText($message));

        $message->setBody('test text');
        $this->assertEquals('test text', $this->transport->getBodyText($message));
    }

    public function testGetTags()
    {
        $message = new Mail\Message;

        $this->assertEmpty($this->transport->getTags($message));

        $message->getHeaders()->addHeader(Mail\Header\GenericHeader::fromString("postmark-tag: test"));
        $this->assertEquals('test', $this->transport->getTags($message));

        $message->getHeaders()->addHeader(Mail\Header\GenericHeader::fromString('postmark-tag: test1'));
        $this->assertEquals('test,test1', $this->transport->getTags($message));
    }
}
