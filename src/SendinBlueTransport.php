<?php

namespace Webup\LaravelSendinBlue;

use Illuminate\Mail\Transport\Transport;
use SendinBlue\Client\Api\SMTPApi;
use SendinBlue\Client\Model\SendSmtpEmail;
use SendinBlue\Client\Model\SendSmtpEmailAttachment;
use SendinBlue\Client\Model\SendSmtpEmailBcc;
use SendinBlue\Client\Model\SendSmtpEmailCc;
use SendinBlue\Client\Model\SendSmtpEmailReplyTo;
use SendinBlue\Client\Model\SendSmtpEmailSender;
use SendinBlue\Client\Model\SendSmtpEmailTo;
use Swift_Attachment;
use Swift_Mime_Headers_UnstructuredHeader;
use Swift_Mime_SimpleMessage;
use Swift_MimePart;

class SendinBlueTransport extends Transport
{
    /**
     * The SendinBlue instance.
     *
     * @var \SendinBlue\Client\Api\SMTPApi
     */
    protected $api;

    /**
     * Create a new SendinBlue transport instance.
     *
     * @param  \SendinBlue\Client\Api\SMTPApi  $mailin
     * @return void
     */
    public function __construct(SMTPApi $api)
    {
        $this->api = $api;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->beforeSendPerformed($message);

        $this->api->sendTransacEmail($this->buildData($message));

        return 0;
    }

    /**
     * Transforms Swift_Message into SendinBlue's email
     * cf. https://github.com/sendinblue/APIv3-php-library/blob/master/docs/Model/SendSmtpEmail.md
     *
     * @param  Swift_Mime_SimpleMessage $message
     * @return SendinBlue\Client\Model\SendSmtpEmail
     */
    protected function buildData($message)
    {
        $smtpEmail = new SendSmtpEmail();

        if ($message->getFrom()) {
            $from = $message->getFrom();
            reset($from);
            $key = key($from);
            $smtpEmail->setSender(new SendSmtpEmailSender([
                'email' => $key,
                'name' => $from[$key],
            ]));
        }

        if ($message->getTo()) {
            $to = [];
            foreach ($message->getTo() as $email => $name) {
                $to[] = new SendSmtpEmailTo([
                    'email' => $email,
                    'name' => $name,
                ]);
            }
            $smtpEmail->setTo($to);
        }

        if ($message->getCc()) {
            $cc = [];
            foreach ($message->getCc() as $email => $name) {
                $cc[] = new SendSmtpEmailCc([
                    'email' => $email,
                    'name' => $name,
                ]);
            }
            $smtpEmail->setCC($cc);
        }

        if ($message->getBcc()) {
            $bcc = [];
            foreach ($message->getBcc() as $email => $name) {
                $bcc[] = new SendSmtpEmailBcc([
                    'email' => $email,
                    'name' => $name,
                ]);
            }
            $smtpEmail->setBcc($bcc);
        }

        // set content
        $html = null;
        $text = null;
        if ($message->getContentType() == 'text/plain') {
            $text = $message->getBody();
        } else {
            $html = $message->getBody();
        }

        $children = $message->getChildren();
        foreach ($children as $child) {
            if ($child instanceof Swift_MimePart && $child->getContentType() == 'text/plain') {
                $text = $child->getBody();
            }
        }

        if ($text === null) {
            $text = strip_tags($message->getBody());
        }

        if ($html !== null) {
            $smtpEmail->setHtmlContent($html);
        }
        $smtpEmail->setTextContent($text);
        // end set content

        if ($message->getSubject()) {
            $smtpEmail->setSubject($message->getSubject());
        }

        if ($message->getReplyTo()) {
            $replyTo = [];
            foreach ($message->getReplyTo() as $email => $name) {
                $replyTo[] = new SendSmtpEmailReplyTo([
                    'email' => $email,
                    'name' => $name,
                ]);
            }
            $smtpEmail->setReplyTo($replyTo);
        }

        $attachment = [];
        foreach ($message->getChildren() as $child) {
            if ($child instanceof Swift_Attachment) {
                $attachment[] = new SendSmtpEmailAttachment([
                    'name' => $child->getFilename(),
                    'content' => chunk_split(base64_encode($child->getBody()))
                ]);
            }
        }
        if (count($attachment)) {
            $smtpEmail->setAttachment($attachment);
        }

        if ($message->getHeaders()) {
            $headers = [];

            foreach ($message->getHeaders()->getAll() as $header) {
                if ($header instanceof Swift_Mime_Headers_UnstructuredHeader) {
                    // remove content type because it creates conflict with content type sets by sendinblue api
                    if ($header->getFieldName() != 'Content-Type') {
                        $headers[$header->getFieldName()] = $header->getValue();
                    }
                }
            }
            $smtpEmail->setHeaders($headers);
        }

        return $smtpEmail;
    }
}
