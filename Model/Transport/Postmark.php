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

use Zend\Mail\Message as ZendMessage;
use Zend\Mail\Transport\Sendmail;
use Zend\Mail\Header\HeaderInterface;
use Zend\Mime\Mime;
use Zend\Mime\Message as MimeMessage;
use Zend\Mail\Transport\Exception as MailException;

class Postmark extends Sendmail
{
    /**
     * Postmark API Uri
     */
    const API_URI = 'https://api.postmarkapp.com/';

    /**
     * Limit of recipients per message in total.
     */
    const RECIPIENTS_LIMIT = 20;

    /**
     * Postmark API key
     *
     * @var string
     */
    protected $_apiKey = null;

    /**
     * HTTP client instance
     *
     * @var \Zend_Http_Client
     */
    protected $_client = null;

    /**
     * @var \SUMOHeavy\Postmark\Helper\Data
     */
    protected $_helper;

    public function __construct(
        \SUMOHeavy\Postmark\Helper\Data $helper
    ) {
        $this->_helper = $helper;
        $apiKey = $this->_helper->getApiKey();

        if (empty($apiKey)) {
            throw new Exception(__CLASS__ . ' requires API key');
        }
        $this->_apiKey = $apiKey;
        $this->callable = [$this, '_sendMail'];
    }

    /**
     * Send request to Postmark service
     *
     * @link http://developer.postmarkapp.com/developer-build.html
     * @return stdClass
     */
    public function _sendMail(ZendMessage $message)
    {
        $data = array(
            'From' => $this->getFrom($message),
            'To' => $this->getTo($message),
            'Cc' => $this->getCc($message),
            'Bcc' => $this->getBcc($message),
            'Subject' => $this->getSubject($message),
            'ReplyTo' => $this->getReplyTo($message),
            'HtmlBody' => $this->getBodyText($message),
            'TextBody' => $this->getBodyText($message),
            'tag' => $this->getTags($message),
            'Attachments' => $this->getAttachments($message),
        );

        $response = $this->prepareHttpClient('/email')
            ->setMethod(\Zend_Http_Client::POST)
            ->setRawData(\Zend_Json::encode($data))
            ->request();
        return $this->_parseResponse($response);
    }

    /**
     * Get a http client instance
     *
     * @param string $path
     * @return \Zend_Http_Client
     */
    protected function prepareHttpClient($path)
    {
        return $this->getHttpClient()->setUri(self::API_URI . $path);
    }

    /**
     * Returns http client object
     *
     * @return \Zend_Http_Client
     */
    public function getHttpClient()
    {
        if (null === $this->_client) {
            $this->_client = new \Zend_Http_Client();
            $headers = array(
                'Accept' => 'application/json',
                'X-Postmark-Server-Token' => $this->_apiKey,
            );
            $this->_client->setMethod(\Zend_Http_Client::GET)
                ->setHeaders($headers);
        }
        return $this->_client;
    }

    /**
     * Parse response object and check for errors
     *
     * @param \Zend_Http_Response $response
     * @return stdClass
     */
    protected function _parseResponse(\Zend_Http_Response $response)
    {
        if ($response->isError()) {
            switch ($response->getStatus()) {
                case 401:
                    throw new Exception('Postmark request error: Unauthorized - Missing or incorrect API Key header.');
                    break;
                case 422:
                    $error = \Zend_Json::decode($response->getBody());
                    if (is_object($error)) {
                        throw new Exception(sprintf('Postmark request error: Unprocessable Entity - API error code %s, message: %s', $error->ErrorCode, $error->Message));
                    } else {
                        throw new Exception(sprintf('Postmark request error: Unprocessable Entity - API error code %s, message: %s', $error['ErrorCode'], $error['Message']));
                    }
                    break;
                case 500:
                    throw new Exception('Postmark request error: Postmark Internal Server Error');
                    break;
                default:
                    throw new Exception('Unknown error during request to Postmark server');
            }
        }
        return \Zend_Json::decode($response->getBody());
    }

    /**
     * Get mail From
     *
     * @return string
     */
    public function getFrom(ZendMessage $message)
    {
        $headers = $message->getHeaders();
        $hasFrom = $headers->has('from');

        if (! $hasFrom) {
            throw new MailException\RuntimeException(
                'Invalid email; contains no "From" header'
            );
        }

        /** @var Mail\Header\From $from */
        $from   = $headers->get('from');
        $list = $from->getAddressList();
        if (0 == count($list)) {
            throw new MailException\RuntimeException('Invalid "From" header; contains no addresses');
        }

        // If not on Windows, return normal string
        if (! $this->isWindowsOs()) {
            return $from->getFieldValue(HeaderInterface::FORMAT_ENCODED);
        }

        // Otherwise, return list of emails
        $addresses = [];
        foreach ($list as $address) {
            $addresses[] = $address->getEmail();
        }
        $addresses = implode(', ', $addresses);
        return $addresses;
    }

    /**
     * Get mail To
     *
     * @return string
     */
    public function getTo(ZendMessage $message)
    {
        $headers = $message->getHeaders();

        $hasTo = $headers->has('to');
        if (! $hasTo && ! $headers->has('cc') && ! $headers->has('bcc')) {
            throw new MailException\RuntimeException(
                'Invalid email; contains no at least one of "To", "Cc", and "Bcc" header'
            );
        }

        if (! $hasTo) {
            return '';
        }

        /** @var Mail\Header\To $to */
        $to   = $headers->get('to');
        $list = $to->getAddressList();
        if (0 == count($list)) {
            throw new MailException\RuntimeException('Invalid "To" header; contains no addresses');
        }

        // If not on Windows, return normal string
        if (! $this->isWindowsOs()) {
            return $to->getFieldValue(HeaderInterface::FORMAT_ENCODED);
        }

        // Otherwise, return list of emails
        $addresses = [];
        foreach ($list as $address) {
            $addresses[] = $address->getEmail();
        }
        $addresses = implode(', ', $addresses);
        return $addresses;
    }

    /**
     * Get mail Cc
     *
     * @return string
     */
    public function getCc(ZendMessage $message)
    {
        $headers = $message->getHeaders();

        $hasCc = $headers->has('cc');
        if (! $hasCc && ! $headers->has('to') && ! $headers->has('bcc')) {
            throw new MailException\RuntimeException(
                'Invalid email; contains no at least one of "To", "Cc", and "Bcc" header'
            );
        }

        if (! $hasCc) {
            return '';
        }

        /** @var Mail\Header\Cc $cc */
        $cc   = $headers->get('cc');
        $list = $cc->getAddressList();
        if (0 == count($list)) {
            throw new MailException\RuntimeException('Invalid "To" header; contains no addresses');
        }

        // If not on Windows, return normal string
        if (! $this->isWindowsOs()) {
            return $cc->getFieldValue(HeaderInterface::FORMAT_ENCODED);
        }

        // Otherwise, return list of emails
        $addresses = [];
        foreach ($list as $address) {
            $addresses[] = $address->getEmail();
        }
        $addresses = implode(', ', $addresses);
        return $addresses;
    }

    /**
     * Get mail Bcc
     *
     * @return string
     */
    public function getBcc(ZendMessage $message)
    {
        $headers = $message->getHeaders();

        $hasBcc = $headers->has('bcc');
        if (! $hasBcc && ! $headers->has('cc') && ! $headers->has('to')) {
            throw new MailException\RuntimeException(
                'Invalid email; contains no at least one of "To", "Cc", and "Bcc" header'
            );
        }

        if (! $hasBcc) {
            return '';
        }

        /** @var Mail\Header\Bcc $bcc */
        $bcc   = $headers->get('bcc');
        $list = $bcc->getAddressList();
        if (0 == count($list)) {
            throw new MailException\RuntimeException('Invalid "To" header; contains no addresses');
        }

        // If not on Windows, return normal string
        if (! $this->isWindowsOs()) {
            return $bcc->getFieldValue(HeaderInterface::FORMAT_ENCODED);
        }

        // Otherwise, return list of emails
        $addresses = [];
        foreach ($list as $address) {
            $addresses[] = $address->getEmail();
        }
        $addresses = implode(', ', $addresses);
        return $addresses;
    }

    /**
     * Get mail Reply To
     *
     * @return string
     */
    public function getReplyTo(ZendMessage $message)
    {
        $headers = $message->getHeaders();

        $hasReplyTo = $headers->has('reply-to');

        if (! $hasReplyTo) {
            return '';
        }

        /** @var Mail\Header\ReplyTo $to */
        $replyTo   = $headers->get('reply-to');
        $list = $replyTo->getAddressList();
        if (0 == count($list)) {
            throw new MailException\RuntimeException('Invalid "To" header; contains no addresses');
        }

        // If not on Windows, return normal string
        if (! $this->isWindowsOs()) {
            return $replyTo->getFieldValue(HeaderInterface::FORMAT_ENCODED);
        }

        // Otherwise, return list of emails
        $addresses = [];
        foreach ($list as $address) {
            $addresses[] = $address->getEmail();
        }
        $addresses = implode(', ', $addresses);
        return $addresses;
    }

    /**
     * Get mail subject
     *
     * @return string
     */
    public function getSubject(ZendMessage $message)
    {
        $headers = $message->getHeaders();
        if (! $headers->has('subject')) {
            return;
        }
        $header = $headers->get('subject');
        return $header->getFieldValue(HeaderInterface::FORMAT_ENCODED);
    }

    /**
     * Get mail body
     *
     * @return string
     */
    public function getBodyText(ZendMessage $message)
    {
        if (! $this->isWindowsOs()) {
            // *nix platforms can simply return the body text
            return $message->getBodyText();
        }

        // On windows, lines beginning with a full stop need to be fixed
        $text = $message->getBodyText();
        $text = str_replace("\n.", "\n..", $text);
        return $text;
    }

    /**
     * Get mail Tag
     *
     * @return string
     */
    public function getTags(ZendMessage $message)
    {
        $headers = $message->getHeaders();
        $tags = array();
        if ($headers->has('postmark-tag')) {
            if($headers->get('postmark-tag') instanceof \ArrayIterator) {
                foreach ($headers->get('postmark-tag') as $key => $val) {
                    $tags[] = $val->getFieldValue();
                }
            }
            else {
                $tags[] = $headers->get('postmark-tag')->getFieldValue();
            }
        }
        return implode(',', $tags);
    }

    /**
     * Get mail Attachments
     *
     * @return array
     */
    public function getAttachments(ZendMessage $message)
    {
        $attachments = array();
        $body = $message->getBody();

        if($body instanceof MimeMessage)
        {
            $parts = $body->getParts();

            if (is_array($parts)) {
                $i = 0;
                foreach ($parts as $part) {
                    if($part->getFileName() != '' && $part->getDisposition() === Mime::DISPOSITION_ATTACHMENT) {
                        $attachments[$i] = array(
                            'ContentType' => $part->getType(),
                            'Name' => $part->getFileName(),
                            'Content' => $part->getContent()
                        );
                        $i++;
                    }
                }
            }
        }

        return $attachments;
    }

    /**
     * Send a message
     *
     * @param  \Zend\Mail\Message $message
     */
    public function send(ZendMessage $message)
    {
        call_user_func($this->callable, $message);
    }
}
