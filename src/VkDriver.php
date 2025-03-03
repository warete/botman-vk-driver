<?php

namespace BotMan\Drivers\Vk;

use BotMan\BotMan\Drivers\Events\GenericEvent;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\BotMan\Messages\Outgoing\Question;
use BotMan\BotMan\Users\User;
use BotMan\Drivers\Vk\Exceptions\VkException;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class VkDriver extends HttpDriver
{
    const DRIVER_NAME = 'Vk';
    const API_URL = 'https://api.vk.com/method/';
    const GENERIC_EVENTS = [
        'chat_create',
        'chat_invite_user',
        'chat_invite_user_by_link',
        'chat_kick_user',
        'chat_photo_remove',
        'chat_photo_update',
        'chat_pin_message',
        'chat_title_update',
        'chat_unpin_message',
        'message_allow',
        'message_deny',
        'message_edit',
        'message_reply',
    ];
	const CONFIRMATION_TYPE = 'confirmation';
	const MESSAGE_NEW_TYPE = 'message_new';

    protected $messages = [];

	protected static $needOkResponse = true;

    public function buildPayload(Request $request)
    {
		$this->payload = new ParameterBag(json_decode($request->getContent(), true) ?? []);
		$this->event = Collection::make($this->payload->all());
		$this->config = Collection::make($this->config->get('vk', []));
		if ($this->matchesRequest() || $this->hasMatchingEvent()) {
			if ($this->event->get('type') == self::CONFIRMATION_TYPE)
			{
				static::$needOkResponse = false;
			}
			$this->respondApiServer();
		}
    }

    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
		//TODO: Implement buttons and attachments features.
		$groupId = $this->event->get('group_id');
		$payload = [
			'user_id' => $matchingMessage->getSender(),
			'peer_id' => '-'.$groupId,
			'random_id' => date("Ymdhisu")
		];

		if ($this->event->get('type') == self::CONFIRMATION_TYPE) {
			$payload = $this->config->get('confirmation_code');
		}else if ($message instanceof Question) {
			$payload['message'] = $message->getText();
		} elseif ($message instanceof OutgoingMessage) {
			$payload['message'] = $message->getText();
		} else {
			$payload['message'] = $message;
		}

		return $payload;
    }

    public function getConversationAnswer(IncomingMessage $message)
    {
        // TODO: Implement interactive features.
        return Answer::create($message->getText())->setMessage($message);
    }

    public function getMessages()
    {
        if (empty($this->messages)) {
            $this->loadMessages();
        }
        return $this->messages;
    }

    public function getUser(IncomingMessage $matchingMessage)
    {
        $payload = [
            'user_ids' => $matchingMessage->getSender(),
            'fields' => 'screen_name'
        ];

        $response = $this->sendRequest('users.get', $payload, new IncomingMessage('', '', ''));
        $responseData = json_decode($response->getContent(), true);

        if ($response->getStatusCode() != 200) {
            throw new VkException('HTTP error occured.', $response->getStatusCode());
        } elseif (isset($responseData['error'])) {
            throw new VkException('Vk API error occured.', $response['error']['error_code']);
        }
        $user = $responseData['response'][0];

        return new User($user['id'], $user['first_name'], $user['last_name'], $user['screen_name']);
    }

    public function hasMatchingEvent()
    {
        if(!$this->requestAuthenticated()) {
            return false;
        }
        $event = false;

        // At first we check "direct" events from vk API, such as
        // confirmation or message_edit. After that we check chat's
        // events (that vk API send inside message_new event).
        if (in_array($this->event->get('type'), self::GENERIC_EVENTS)) {
            $event = new GenericEvent($this->event->get('object') ?? []);
            $event->setName($this->event->get('type'));
        } elseif (in_array($this->event->toArray()['object']['action']['type'] ?? '', self::GENERIC_EVENTS)) {
            $chatAction = Collection::make($this->event->toArray()['object']['action']);
            $event = new GenericEvent($chatAction->except('type'));
            $event->setName($this->event->toArray()['object']['action']['type']);
        }

        return $event;
    }

    public function isConfigured()
    {
		return (
			!empty($this->config->get('access_token'))
			&&
			!empty($this->config->get('api_version'))
			&&
			!empty($this->config->get('confirmation_code'))
		);
    }

    protected function loadMessages()
    {
        $message = $this->event->get('object')['message'];

        $this->messages = [new IncomingMessage($message['text'], $message['from_id'], $message['peer_id'], $this->event->toArray())];
    }

    public function matchesRequest()
    {
		return (
			$this->event->get('type') == self::CONFIRMATION_TYPE
			||
			(
				($this->event->get('type') == self::MESSAGE_NEW_TYPE)
				&&
				!isset($this->event->toArray()['object']['action'])
				&&
				$this->requestAuthenticated()
			)
		);
    }

    protected function respondApiServer()
    {
		if (static::$needOkResponse) {
			echo 'ok';
			static::$needOkResponse = false;
		}
    }

    public function requestAuthenticated()
    {
        return empty($this->config->get('secret_key')) || ($this->config->get('secret_key') == $this->event->get('secret'));
    }

    public function sendPayload($payload)
    {
		if ($this->event->get('type') == self::CONFIRMATION_TYPE)
		{
			exit($this->config->get('confirmation_code'));
		}
		else
		{
			return $this->sendRequest('messages.send', $payload, new IncomingMessage('', '', ''));
		}
    }

    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        $parameters['access_token'] = $this->config->get('access_token');
        $parameters['v'] = $this->config->get('api_version');
        $parameters['lang'] = $this->config->get('lang');
        return $this->http->post(self::API_URL . $endpoint, [], $parameters);
    }
}