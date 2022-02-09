<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Jaeger;

use InvalidArgumentException;
use OpenTelemetry\SDK\Trace\Behavior\SpanExporterTrait;
use OpenTelemetry\SDK\Trace\Behavior\UsesSpanConverterTrait;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use Psr\Http\Client\ClientInterface;

class HttpCollectorExporter implements SpanExporterInterface
{
    use UsesSpanConverterTrait;
    use SpanExporterTrait;

    private SpanConverter $spanConverter;

    private ThriftHttpSender $sender;

    public function __construct(
        $name,
        string $endpointUrl,
        ClientInterface $client
    ) {
        $parsedDsn = parse_url($endpointUrl);

        if (!is_array($parsedDsn)) {
            throw new InvalidArgumentException('Unable to parse provided DSN');
        }

        if (!isset($parsedDsn['host']) || !isset($parsedDsn['port'])) {
            throw new InvalidArgumentException('Endpoint should have host, port');
        }

        $this->sender = new ThriftHttpSender(
            $client,
            $name,
            $parsedDsn['host'],
            $parsedDsn['port'],
            isset($parsedDsn['path']) ? $parsedDsn['path'] : '', //Matching THttpClient's default
            isset($parsedDsn['scheme']) ? $parsedDsn['scheme'] : 'http' //Matching THttpClient's default
        );

        $this->spanConverter = new SpanConverter();
    }

    /**
     * @psalm-return SpanExporterInterface::STATUS_*
     */
    public function doExport(iterable $spans): int
    {
        $thriftSpans = $this->spanConverter->convert($spans);

        $this->sender->send($thriftSpans);

        return SpanExporterInterface::STATUS_SUCCESS;
    }

    /** @inheritDoc */
    public static function fromConnectionString(string $endpointUrl, string $name, $args = null): HttpCollectorExporter
    {
        return new HttpCollectorExporter(
            $name,
            $endpointUrl
        );
    }
}
