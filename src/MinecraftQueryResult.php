<?php

declare(strict_types=1);

namespace PHPMinecraft\MinecraftQuery;

class MinecraftQueryResult
{
	/** @var string */
	private $version;

	/** @var int */
	private $protocolVersion;

	/** @var int */
	private $onlinePlayers;

	/** @var int */
	private $maxPlayers;

	/** @var array */
	private $playersSample;

	/** @var string */
	private $messageOfTheDay;

	/** @var string */
	private $favicon;

	/** @var int */
	private $latency;

	public function __construct(
		string $version,
		int $protocolVersion,
		int $onlinePlayers,
		int $maxPlayers,
		array $playersSample,
		string $messageOfTheDay,
		int $latency,
		?string $favicon
	) {
		$this->version = $version;
		$this->protocolVersion = $protocolVersion;
		$this->onlinePlayers = $onlinePlayers;
		$this->maxPlayers = $maxPlayers;
		$this->playersSample = $playersSample;
		$this->messageOfTheDay = $messageOfTheDay;
		$this->latency = $latency;
		$this->favicon = $favicon;
	}

	public function getVersion(): string
	{
		return $this->version;
	}

	public function getProtocolVersion(): int
	{
		return $this->protocolVersion;
	}

	public function getOnlinePlayers(): int
	{
		return $this->onlinePlayers;
	}

	public function getMaxPlayers(): int
	{
		return $this->maxPlayers;
	}

	public function getPlayersSample(): array
	{
		return $this->playersSample;
	}

	public function getMessageOfTheDay(): string
	{
		return $this->messageOfTheDay;
	}

	public function getFavicon(): ?string
	{
		return $this->favicon;
	}

	public function getLatency(): int
	{
		return $this->latency;
	}
	
	public static function fromRawData(array $rawData): self
	{
		return new MinecraftQueryResult(
			isset($rawData['version']['name']) ? $rawData['version']['name'] : 'Unknown version',
			isset($rawData['version']['protocol']) ? (int) $rawData['version']['protocol'] : 0,
			isset($rawData['players']['online']) ? (int) $rawData['players']['online'] : 0,
			isset($rawData['players']['max']) ? (int) $rawData['players']['max'] : 0,
			isset($rawData['players']['sample']) ? (array) $rawData['players']['sample'] : [],
			isset($rawData['description']['text']) ? (string) $rawData['description']['text'] : '',
			$rawData['latency'],
			isset($rawData['favicon']) ? (string) $rawData['favicon'] : null
		);
	}
}
