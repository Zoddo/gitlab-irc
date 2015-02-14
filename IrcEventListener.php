<?php

namespace Zoddo\gitlab\irc;

use Zoddo\irc\IrcConnection;

/**
 * Class IrcEventListener
 * @package Zoddo\gitlab\irc
 */
class IrcEventListener
{
	/**
	 * @param array $data
	 * @param IrcConnection $connection
	 */
	public function NickAlreadyUse(array $data, IrcConnection $connection)
	{
		$connection->send(sprintf('NICK %s%d', $data['params'][0], mt_rand(100, 999)));
	}
}