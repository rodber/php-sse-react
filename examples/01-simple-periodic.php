<?php

require __DIR__ . '/../vendor/autoload.php';

use Clue\React\Sse\BufferedChannel;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Stream\ThroughStream;

$loop = React\EventLoop\Loop::get();

$channel = new BufferedChannel();

$http = new React\Http\HttpServer($loop, function (ServerRequestInterface $request) use ($channel, $loop) {
    if ($request->getUri()->getPath() === '/') {
        return new Response(
            200,
            array('Content-Type' => 'text/html'),
            file_get_contents(__DIR__ . '/00-eventsource.html')
        );
    }

    if ($request->getUri()->getPath() !== '/demo') {
        return new Response(404);
    }

    echo 'connected' . PHP_EOL;

    $stream = new ThroughStream();

    $id = $request->getHeaderLine('Last-Event-ID');
    $loop->futureTick(function () use ($channel, $stream, $id) {
        $channel->connect($stream, $id);
    });

    $stream->on('close', function () use ($stream, $channel) {
        echo 'disconnected' . PHP_EOL;
        $channel->disconnect($stream);
    });

    return new Response(
        200,
        array('Content-Type' => 'text/event-stream'),
        $stream
    );
});

$socket = new \React\Socket\SocketServer(isset($argv[1]) ? '0.0.0.0:' . $argv[1] : '0.0.0.0:0', [], $loop);
$http->listen($socket);

$loop->addPeriodicTimer(2.0, function () use ($channel) {
    $channel->writeMessage('ticking ' . mt_rand(1, 5) . '...');
});

echo 'Server now listening on ' . $socket->getAddress() . ' (port is first parameter)' . PHP_EOL;
echo 'This will send a message every 2 seconds' . PHP_EOL;

$loop->run();
