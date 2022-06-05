<?php

declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use OpenTelemetry\Contrib\Zipkin\Exporter as ZipkinExporter;
use OpenTelemetry\SDK\Trace\SpanExporter\ConsoleSpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;

use Psr\Http\Message\RequestInterface;

function add_header($header, $value)
{
    return function (callable $handler) use ($header, $value) {
        return function (
            RequestInterface $request,
            array $options
        ) use ($handler, $header, $value) {
            $request = $request->withHeader($header, $value);
            return $handler($request, $options);
        };
    };
}

function add_span_per_request($tracer)
{
    return function (callable $handler) use ($tracer) {
        return function (
            RequestInterface $request,
            array $options
        ) use ($handler, $tracer) {
            //Start a span
            $span = $tracer->spanBuilder('guzzle-request')->startSpan();

            $responsePromise = $handler($request, $options);

            //End a span
            $span->end();

            return $responsePromise;
        };
    };
}

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
// use GuzzleHttp\Client;

$zipkinExporter = new ZipkinExporter(
    'alwaysOnZipkinExample',
    'http://zipkin:9411/api/v2/spans',
    new Client(),
    new HttpFactory(),
    new HttpFactory()
);
$consoleExporter = new ConsoleSpanExporter();
$tracerProvider =  new TracerProvider(
    new SimpleSpanProcessor(
        $consoleExporter
    )
);
$tracer = $tracerProvider->getTracer();

echo 'Starting AlwaysOnZipkinExample';

// $guzzleClient = new Client();

$stack = new HandlerStack();
$stack->setHandler(new CurlHandler());
// $stack->push(add_header('X-Foo', 'bar'));
$stack->push(add_span_per_request($tracer));
$client = new Client(['handler' => $stack]);

$root = $span = $tracer->spanBuilder('root')->startSpan();
$span->activate();

for ($i = 0; $i < 3; $i++) {
    // start a span, register some events
    $span = $tracer->spanBuilder('loop-' . $i)->startSpan();

    // $client->get("https://httpbin.org/get");
    $client->get("https://webhook.site/553e6d59-da93-49a6-9414-2096c93a72b1");

    $span->setAttribute('remote_ip', '1.2.3.4')
        ->setAttribute('country', 'USA');

    $span->addEvent('found_login' . $i, new Attributes([
        'id' => $i,
        'username' => 'otuser' . $i,
    ]));
    $span->addEvent('generated_session', new Attributes([
        'id' => md5((string) microtime(true)),
    ]));

    $span->end();
}
$root->end();
echo PHP_EOL . 'AlwaysOnZipkinExample complete!  See the results at http://localhost:9411/';

echo PHP_EOL;
