<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Jaeger;

use InvalidArgumentException;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use OpenTelemetry\Contrib\Jaeger\JaegerSender;
use JsonException;
use OpenTelemetry\SDK\Trace\Behavior\SpanExporterTrait;
use OpenTelemetry\SDK\Trace\Behavior\UsesSpanConverterTrait;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Psr\Http\Message\RequestInterface;
use Thrift\Exception\TTransportException;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Transport\THttpClient;

class Exporter implements SpanExporterInterface
{
    use UsesSpanConverterTrait;
    use SpanExporterTrait;

    private string $serviceName;

    private SpanConverter $spanConverter;

    private JaegerSender $jaegerSender;

    public function __construct(
        $name,
        string $endpointUrl
    ) {
        $this->serviceName = $name;

        $parsedDsn = parse_url($endpointUrl);

        if (!is_array($parsedDsn)) {
            throw new InvalidArgumentException('Unable to parse provided DSN');
        }

        if (!isset($parsedDsn['host']) || !isset($parsedDsn['port'])) {
            throw new InvalidArgumentException('Endpoint should have host, port');
        }
        
        $transport = new THttpClient(
            $parsedDsn['host'],
            $parsedDsn['port'],
            $endpointUrl
        );

        try {
            $transport->open(); //TODO - figure out if this should go somewhere else
        } catch (TTransportException $e) {
            $this->config->getLogger()->warning($e->getMessage());
        }
        $protocol = new TBinaryProtocol($transport);
        $this->config->getLogger()->debug('Initializing HTTP Jaeger Tracer with Jaeger.Thrift over Binary protocol');
        $this->jaegerSender = new JaegerSender(
            $this->serviceName,
            $protocol, 
            $this->config->getLogger()
        );

        $this->spanConverter = new SpanConverter();
    }

    /**
     * @psalm-return SpanExporterInterface::STATUS_*
     */
    public function doExport(iterable $spans): int
    {
        $thriftSpans = [];
        foreach ($spans as $span) {
            $thriftSpans[] = $this->spanConverter->convert($span);
        }

        $this->jaegerSender->send($thriftSpans);

        return SpanExporterInterface::STATUS_SUCCESS;
    }

    /** @inheritDoc */
    public static function fromConnectionString(string $endpointUrl, string $name, $args = null): AgentExporter
    {
        return new AgentExporter(
            $name,
            $endpointUrl
        );
    }
}