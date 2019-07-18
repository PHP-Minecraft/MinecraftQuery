<?php

declare(strict_types=1);

namespace PHPMinecraft\MinecraftQuery;

use PHPMinecraft\MinecraftQuery\Exception\MinecraftQueryException;

class MinecraftQueryResolver
{
	/** @var string */
	private $host;
	
	/** @var int */
	private $port;
	
	/** @var int */
	private $timeout;

	/** @var array */
	private $rawData;

	public function __construct(string $host, int $port = 25565, $timeout = 2, $resolveSRV = true)
	{
		$this->host = $host;
		$this->port = $port;
		$this->timeout = $timeout;

		if ($resolveSRV) {
			$this->resolveSRV();
		}
	}

	public static function fromAddress(string $host, $timeout = 2, $resolveSRV = true): self
	{
		$addressParts = explode(':', $host);
		$host = $addressParts[0];

		if (count($addressParts) > 1) {
			$port = (int) $addressParts[1];

		} else {
			$port = 25565;
		}

		return new MinecraftQueryResolver($host, $port, $timeout, $resolveSRV);
	}

	/**
	 * @throws MinecraftQueryException
	 */
	public function getResult(bool $tryOldQueryProtocolPre17 = false): MinecraftQueryResult
	{
		return MinecraftQueryResult::fromRawData($this->getRawData($tryOldQueryProtocolPre17));
	}

	/**
	 * @throws MinecraftQueryException
	 */
	public function getRawData(bool $tryOldQueryProtocolPre17 = false): array
	{
		if ($this->rawData === null) {
			$this->retrieveData();
		}

		if ($tryOldQueryProtocolPre17 && $this->rawData === null) {
			$this->retrieveDataPre17();
		}

		return $this->rawData;
	}

	/**
	 * @throws MinecraftQueryException
	 */
	public function retrieveData(): void
	{
		$timeStart = microtime(true);

		$socket = $this->createSocket();

		stream_set_timeout($socket, $this->timeout);

		$preparedData = "\x00";
		$preparedData .= "\x04";
		$preparedData .= pack('c', strlen($this->host)) . $this->host;
		$preparedData .= pack('n', $this->port);
		$preparedData .= "\x01";
		$preparedData = pack('c', strlen($preparedData)) . $preparedData;

		fwrite($socket, $preparedData);
		fwrite($socket, "\x01\x00");

		$length = $this->readPacketLength($socket);

		if ($length < 10) {
			throw new MinecraftQueryException('Packet length is too small');
		}

		fgetc($socket);

		$length = $this->readPacketLength($socket);

		$jsonData = '';
		do {
			if (microtime(true) - $timeStart > $this->timeout) {
				throw new MinecraftQueryException('Server read timed out');
			}

			$remainder = $length - strlen($jsonData);
			$block = fread($socket, $remainder);

			if (!$block) {
				throw new MinecraftQueryException('Server returned too few data');
			}

			$jsonData .= $block;

		} while (strlen($jsonData) < $length);

		fclose($socket);

		$timeEnd = microtime(true);

		if (strlen($jsonData) === 0) {
			throw new MinecraftQueryException('Server did not return any data');
		}

		$this->rawData = (array) json_decode($jsonData, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			if (function_exists('json_last_error_msg')) {
				throw new MinecraftQueryException(json_last_error_msg());
			} else {
				throw new MinecraftQueryException( 'JsonException, server sent invalid json');
			}
		}

		$this->rawData['latency'] = (int) ($timeEnd * 1000 - $timeStart * 1000);
	}

	/**
	 * @throws MinecraftQueryException
	 */
	public function retrieveDataPre17(): void
	{
		$timeStart = microtime(true);

		$socket = $this->createSocket();

		fwrite($socket, "\xFE\x01");
		$data = fread($socket, 512);
		$len = strlen($data);

		if ($len < 4 || $data[0] !== "\xFF") {
			throw new MinecraftQueryException('Server returned too few data');
		}

		$data = substr($data, 3 );
		$data = iconv('UTF-16BE', 'UTF-8', $data);

		fclose($socket);
		$timeEnd = microtime(true);

		if ($data[1] === "\xA7" && $data[2] === "\x31") {
			$data = explode("\x00", $data);
			$this->rawData = [
				'version' => [
					'protocol' => (string) $data[1],
					'name' => (string) $data[2]
				],
				'description' => [
					'text' => (string) $data[3]
				],
				'players' => [
					'online' => (int) $data[4],
					'max' => (int) $data[5]
				]
			];
		} else {
			$data = explode("\xA7", $data);
			$this->rawData = [
				'version' => [
					'protocol' => 39,
					'name' => '1.3'
				],
				'description' => [
					'text' => substr((string) $data[0], 0, -1)
				],
				'players' => [
					'online' => isset($data[1]) ? (int) $data[1] : 0,
					'max' => isset($data[2]) ? (int) $data[2] : 0
				]
			];
		}

		$this->rawData['latency'] = (int) ($timeEnd * 1000 - $timeStart * 1000);
	}

	/**
	 * @throws MinecraftQueryException
	 * @return resource
	 */
	private function createSocket()
	{
		$socket = @fsockopen($this->host, $this->port, $errNo, $errStr, $this->timeout);

		if (!$socket) {
			throw new MinecraftQueryException(sprintf('Failed to create a socket (%s) "%s"', $errNo, $errStr));
		}

		return $socket;
	}

	/**
	 * @param resource $socket
	 * @throws MinecraftQueryException
	 */
	private function readPacketLength($socket): int
	{
		$i = 0;
		$j = 0;

		while (true) {
			$k = @fgetc($socket);

			if ($k === false) {
				return 0;
			}

			$k = ord($k);

			$i |= ($k & 0x7F) << $j++ * 7;

			if ($j > 5) {
				throw new MinecraftQueryException('VarInt is too big');
			}

			if (($k & 0x80) != 128) {
				break;
			}
		}

		return $i;
	}

	private function resolveSRV(): void
	{
		if (filter_var($this->host, FILTER_VALIDATE_IP)) {
			return;
		}

		$dnsRecord = @dns_get_record( '_minecraft._tcp.' . $this->host, DNS_SRV );

		if (!$dnsRecord || empty($dnsRecord)) {
			return;
		}

		if (isset($dnsRecord[0]['target'])) {
			$this->host = $dnsRecord[0]['target'];
		}

		if (isset($dnsRecord[0]['port'])) {
			$this->port = $dnsRecord[0]['port'];
		}
	}
}
