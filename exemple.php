<?php

require 'vendor/autoload.php';

use Zoddo\irc\Config as IrcConfig;
use Zoddo\gitlab\irc\Config;
use Zoddo\gitlab\irc\EventListener;
use Zoddo\gitlab\webhook\Event;

$ircConfig = new IrcConfig('irc.ekinetirc.fr.nf', 'GitLab');
$ircConfig->setPort(6697)
		->setSsl();
$config = new Config($ircConfig, '#Zoddo');

$event = new Event($_POST);
$event->addEventListener(new EventListener($config));
if ($event->gettype() !== null)
{
	$event->execEvent();
}