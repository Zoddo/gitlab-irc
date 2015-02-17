<?php

namespace Zoddo\gitlab\irc;

use Zoddo\gitlab\webhook\EventListener\onPushInterface;
use Zoddo\gitlab\webhook\EventListener\onTagInterface;
use Zoddo\gitlab\webhook\EventListener\onMergeInterface;
use Zoddo\irc\EventListener\PingResponder;
use Zoddo\irc\IrcConnection;

/**
 * Class EventListener
 * @package Zoddo\gitlab\irc
 */
class EventListener implements onPushInterface, onTagInterface, onMergeInterface
{
	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * @var IrcConnection
	 */
	protected $irc = null;

	/**
	 * @param Config $config
	 */
	public function __construct(Config $config)
	{
		$this->config = $config;
		$this->config->getIrcConfig()->setReal('GitLab IRC Bot by Zoddo');
	}

	/**
	 * @param array $data
	 */
	public function onPush(array $data)
	{
		if ($data['after'] == '0000000000000000000000000000000000000000')
		{
			$this->onDelete($data);
			return;
		}
		
		$branch = str_replace('refs/heads/', '', $data['ref']);
		if ($data['total_commits_count'] == 1)
		{
			$url = $data['commits'][0]['url'];
		}
		else
		{
			$url = sprintf('%s/compare/%s...%s', $data['repository']['homepage'], $data['before'], $data['after']);
		}

		$message  = sprintf('[%s] %s ', $this->format('repo', $data['repository']['name']), $this->format('name', $data['user_name']));
		$message .= sprintf('pushed %s new commits to %s: ', $this->format('num', $data['total_commits_count']), $this->format('branch', $branch));
		$message .= $this->format('url', $url);

		$this->sendToIrc($message);

		foreach ($data['commits'] as $commit)
		{
			$hash = substr($commit['id'], 0, 7);
			$short  = substr($commit['message'], 0, strpos($commit['message'], "\n"));
			$short .= (empty($short)) ? $commit['message'] : '...';

			$message  = sprintf('%s/%s ', $this->format('repo', $data['repository']['name']), $this->format('branch', $branch));
			$message .= sprintf('%s %s: %s', $this->format('hash', $hash), $this->format('name', $commit['author']['name']), $short);

			$this->sendToIrc($message);
		}
	}

	/**
	 * @param array $data
	 */
	public function onTag(array $data)
	{
		if ($data['after'] == '0000000000000000000000000000000000000000')
		{
			$this->onDelete($data);
			return;
		}

		$tag = str_replace('refs/tags/', '', $data['ref']);
		$hash = substr($data['after'], 0, 7);
		$url = sprintf('%s/commits/%s', $data['repository']['homepage'], $tag);

		$message  = sprintf('[%s] %s ', $this->format('repo', $data['repository']['name']), $this->format('name', $data['user_name']));
		$message .= sprintf('tagged %s at %s: %s', $this->format('tag', $tag), $this->format('hash', $hash), $url);

		$this->sendToIrc($message);
	}

	/**
	 * @param array $data
	 */
	protected function onDelete(array $data)
	{
		$ref = str_replace(array('refs/heads/', 'refs/tags/'), '', $data['ref']);
		$hash = substr($data['before'], 0, 7);
		$url = sprintf('%s/commit/%s', $data['repository']['homepage'], $data['before']);

		$message  = sprintf('[%s] %s ', $this->format('repo', $data['repository']['name']), $this->format('name', $data['user_name']));
		$message .= sprintf('deleted %s at %s: %s', $this->format('tag', $ref), $this->format('hash', $hash), $url);

		$this->sendToIrc($message);
	}

	/**
	 * @param array $data
	 */
	public function onMerge(array $data)
	{
		$source = $data['object_attributes']['source_branch'];
		$target = $data['object_attributes']['target_branch'];
		if ($data['object_attributes']['source_project_id'] != $data['object_attributes']['target_project_id'])
		{
			$source = $data['object_attributes']['source']['namespace'] . ':' . $source;
			$target = $data['object_attributes']['target']['namespace'] . ':' . $target;
		}
		$homepage = preg_replace('#^(.+)\.git$#i', '$1', $data['object_attributes']['target']['http_url']);
		$url = sprintf('%s/merge_requests/%s', $homepage, $data['object_attributes']['id']);

		$message  = sprintf('[%s] %s ', $this->format('repo', $data['object_attributes']['target']['name']), $this->format('name', $data['user']['name']));
		$message .= sprintf('merged %s into %s: ', $this->format('branch', $source), $this->format('branch', $target));
		$message .= $this->format('url', $url);

		$this->sendToIrc($message);
	}

	/**
	 * @param string $type
	 * @param string $data
	 * @return string
	 */
	protected function format($type, $data)
	{
		if (!$this->config->getColors())
		{
			return $data;
		}

		/*
		 * IRC message formatting.  For reference:
		 * \002 bold   \003 color   \017 reset  \026 italic/reverse  \037 underline
		 * 00 white          01 black        02 dark blue        03 dark green
		 * 04 dark red       05 brownish     06 dark purple      07 orange
		 * 08 yellow         09 light green  10 dark teal        11 light teal
		 * 12 light blue     13 light purple 14 dark gray        15 light gray
		 */

		switch ($type)
		{
			case 'url':
				return sprintf("\00302\037%s\017", $data);

			case 'repo':
				return sprintf("\00313%s\017", $data);

			case 'name': // Committer
				return sprintf("\00315%s\017", $data);

			case 'branch':
			case 'tag':
				return sprintf("\00306%s\017", $data);

			case 'hash': // Commit ID
				return sprintf("\00314%s\017", $data);

			case 'num':
			case 'number':
				return sprintf("\002%d\017", $data);

			default:
				return $data;
		}
	}

	/**
	 * @param $message
	 * @return $this
	 */
	protected function sendToIrc($message)
	{
		if (!$this->irc)
		{
			$this->irc = new IrcConnection($this->config->getIrcConfig());
			$this->irc->getEvent()->addListener('ping', array(new PingResponder, 'onPing'))
								->addListener('433', array(new IrcEventListener, 'NickAlreadyUse'));
			$this->irc->connect();

			$start = time();
			do
			{
				$data = $this->irc->irc_read(3);
				if ($data === false)
				{
					continue;
				}
				$this->irc->callListeners($data);
			} while(!$this->irc->feof() && ($data === false || $data['command'] != '001') && (time() - $start) < 5);

			if ($this->config->getNickServPassword())
			{
				$this->irc->privmsg('NickServ', sprintf('IDENTIFY %s', $this->config->getNickServPassword()));
				sleep(1); // The time that the identification is treated by NickServ
			}
			if ($this->config->getJoin())
			{
				$this->irc->join($this->config->getChannel(), $this->config->getPassword());
			}
		}

		if ($this->config->getNotice())
		{
			$this->irc->notice($this->config->getChannel(), $message);
		}
		else
		{
			$this->irc->privmsg($this->config->getChannel(), $message);
		}

		return $this;
	}

	public function __destruct()
	{
		if ($this->irc && !$this->irc->feof())
		{
			if ($this->config->getJoin())
			{
				$this->irc->part($this->config->getChannel());
			}
			$this->irc->quit('GitLab IRC Bot by Zoddo');

			$start = time();
			while(!$this->irc->feof() && (time() - $start) < 30) // After 30 seconds, we force-disconnect
			{
				$data = $this->irc->irc_read(3);
				if ($data === false)
				{
					continue;
				}
				$this->irc->callListeners($data);
			}
		}
	}
}