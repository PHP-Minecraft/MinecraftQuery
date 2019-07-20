# MinecraftQuery
🛰️ Minecraft PHP library for retrieving server query data

# Installation
Required at least PHP 7.2
```
composer require php-minecraft/minecraft-query
```

# Example usage

```PHP
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use PHPMinecraft\MinecraftQuery\MinecraftQueryResolver;
```

## Minecraft v1.7+
```PHP
$resolver = new MinecraftQueryResolver('play.minecord.net', 25565);

$result = $resolver->getResult();

```

## Minecraft <= v1.6
```PHP
$resolver = new MinecraftQueryResolver('play.minecord.net', 25565);

$resolver->retrieveDataPre17();

$result = $resolver->getResult();

```

## Autodetect minecraft version (if 1.7+ query fails, older will be used)
```PHP
$resolver = new MinecraftQueryResolver('play.minecord.net', 25565);

$result = $resolver->getResult($tryOldQueryProtocolPre17 = true);

```

## Result data
```PHP
$result->getMaxPlayers() // integer
$result->getOnlinePlayers() // integer
$result->getPlayersSample() // array (players -> sample at https://wiki.vg/Server_List_Ping)
$result->getVersion() // string
$result->getProtocolVersion() // integer
$result->getMessageOfTheDay() // string
$result->getLatency() // integer (ms)
$result->getFavicon() // string or null
```


## Tweaks
```PHP
// use this cosntructor if you dont want separate address to host and port
$resolver = MinecraftQueryResolver::fromAddress('play.minecord.net');

// use this method if you want raw data in array retrieved from minecraft server
$rawData = $resolver->getRawData();

// use this method if you want construct MinecraftQueryResult from raw data
$result = MinecraftQueryResult::fromRawData($rawData);
```

Library (socket logic) is inspired by xPaw/PHP-Minecraft-Query
