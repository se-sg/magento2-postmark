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

use Zend\Mail\Message as ZendMessage;

class Transport extends \Magento\Framework\Mail\Transport implements \Magento\Framework\Mail\TransportInterface
{
    /**
     * @var \Magento\Framework\Mail\MessageInterface
     */
    protected $_message;

    /**
     * @var \SUMOHeavy\Postmark\Helper\Data
     */
    protected $_helper;

    /**
     * @var \SUMOHeavy\Postmark\Model\Transport\Postmark
     */
    protected $_transportPostmark;

    /**
     * @param \Magento\Framework\Mail\MessageInterface $message
     * @param \SUMOHeavy\Postmark\Helper\Data $helper
     * @param null $parameters
     */
    public function __construct(
        \Magento\Framework\Mail\MessageInterface $message,
        \SUMOHeavy\Postmark\Model\Transport\Postmark $transportPostmark,
        \SUMOHeavy\Postmark\Helper\Data $helper,
        $parameters = null
    ) {
        $this->_helper  = $helper;
        $this->_transportPostmark = $transportPostmark;

        if ($this->_helper->canUse()) {
            $this->_message = $message;
        } else {
            parent::__construct($message, $parameters);
        }
    }

    /**
     * Send a mail using this transport
     *
     * @return void
     * @throws \Magento\Framework\Exception\MailException
     */
    public function sendMessage()
    {
        if (!$this->_helper->canUse()) {
            parent::sendMessage();
            return;
        }

        try {
            $this->_transportPostmark->send(
                ZendMessage::fromString($this->_message->getRawMessage())
            );
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\MailException(new \Magento\Framework\Phrase($e->getMessage()), $e);
        }
    }
}
