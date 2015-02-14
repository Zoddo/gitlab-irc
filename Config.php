<?php

namespace Zoddo\gitlab\irc;

class Config
{
	/**
	 * @var \Zoddo\irc\Config
	 */
	protected $ircConfig;

	/**
	 * @var string
	 */
	protected $channel;

	/**
	 * @var string
	 */
	protected $password = '';

	/**
	 * @var bool
	 */
	protected $notice = false;

	/**
	 * @var bool
	 */
	protected $join = true;

	/**
	 * @var string
	 */
	protected $nickServPassword = null;

	/**
	 * @var bool
	 */
	protected $colors = true;

	/**
	 * @param \Zoddo\irc\Config $ircConfig
	 * @param string $channel
	 * @param bool $notice
	 * @param bool $join
	 */
	public function __construct(\Zoddo\irc\Config $ircConfig, $channel, $notice = false, $join = true)
	{
		$this->ircConfig = $ircConfig;
		$this->channel = $channel;
		$this->notice = $notice;
		$this->join = $join;
	}

	/**
	 * @return \Zoddo\irc\Config
	 */
	public function getIrcConfig()
	{
		return $this->ircConfig;
	}

	/**
	 * @return string
	 */
	public function getChannel()
	{
		return $this->channel;
	}

	/**
	 * @param string $password
	 * @return $this
	 */
	public function setPassword($password)
	{
		$this->password = $password;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getPassword()
	{
		return $this->password;
	}

	/**
	 * @return bool
	 */
	public function getNotice()
	{
		return $this->notice;
	}

	/**
	 * @return bool
	 */
	public function getJoin()
	{
		return $this->join;
	}

	/**
	 * @param string $password
	 * @return $this
	 */
	public function setNickServPassword($password = null)
	{
		$this->nickServPassword = $password;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getNickServPassword()
	{
		return $this->nickServPassword;
	}

	/**
	 * @param bool $colors
	 * @return $this
	 */
	public function setColors($colors = true)
	{
		$this->colors = $colors;

		return $this;
	}

	/**
	 * @return bool
	 */
	public function getColors()
	{
		return $this->colors;
	}
}