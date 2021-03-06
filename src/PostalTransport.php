<?php

namespace SynergiTech\Postal;

use Illuminate\Mail\Transport\Transport;

use Postal\SendMessage;
use Postal\Client;
use Postal\Error;

use Swift_Attachment;
use Swift_Image;
use Swift_MimePart;
use Swift_Mime_SimpleMessage;
use Swift_Events_SendEvent;

class PostalTransport extends Transport
{
    protected $client;
    protected $message;

    public function __construct($domain, $key)
    {
        $this->client = new Client($domain, $key);
    }

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null)
    {
        $this->message = new SendMessage($this->client);

        $this->beforeSendPerformed($message);

        $recipients = [];
        foreach (['to', 'cc', 'bcc'] as $type) {
            foreach ((array) $message->{'get' . ucwords($type)}() as $email => $name) {
                if (!in_array($email, $recipients)) {
                    $recipients[] = $email;
                    $this->message->{$type}($name != null ? ($name . ' <' . $email . '>') : $email);
                }
            }
        }

        if ($message->getFrom()) {
            foreach ($message->getFrom() as $email => $name) {
                $this->message->from($name != null ? ($name . ' <' . $email . '>') : $email);
            }
        }

        if ($message->getReplyTo()) {
            foreach ($message->getReplyTo() as $email => $name) {
                $this->message->replyTo($name != null ? ($name . ' <' . $email . '>') : $email);
            }
        }

        if ($message->getSubject()) {
            $this->message->subject($message->getSubject());
        }

        if ($message->getContentType() == 'text/plain') {
            $this->message->plainBody($message->getBody());
        } elseif ($message->getContentType() == 'text/html') {
            $this->message->htmlBody($message->getBody());
        } else {
            foreach ($message->getChildren() as $child) {
                if ($child instanceof Swift_MimePart && $child->getContentType() === 'text/plain') {
                    $this->message->plainBody($child->getBody());
                }
            }
            $this->message->htmlBody($message->getBody());
        }

        foreach ($message->getChildren() as $attachment) {
            if ($attachment instanceof Swift_Attachment) {
                $this->message->attach(
                    $attachment->getFilename(),
                    $attachment->getContentType(),
                    $attachment->getBody()
                );
            } elseif ($attachment instanceof Swift_Image) {
                $this->message->attach(
                    $attachment->getId(),
                    $attachment->getContentType(),
                    $attachment->getBody()
                );
            } else {
                continue;
            }
        }

        try {
            $response = $this->message->send();
        } catch (Error $error) {
            throw new \BadMethodCallException($error->getMessage(), $error->getCode(), $error);
        }

        $this->sendPerformed($message);

        return $response;
    }
}
