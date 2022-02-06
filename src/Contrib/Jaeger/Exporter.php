<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Jaeger;

use InvalidArgumentException;
use OpenTelemetry\Contrib\Jaeger\JaegerSender;
use OpenTelemetry\SDK\Trace\Behavior\SpanExporterTrait;
use OpenTelemetry\SDK\Trace\Behavior\UsesSpanConverterTrait;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;

class Exporter implements SpanExporterInterface
{
    use UsesSpanConverterTrait;
    use SpanExporterTrait;

    private SpanConverter $spanConverter;

    private JaegerSender $jaegerSender;

    public function __construct(
        $name,
        string $endpointUrl
    ) {
        $parsedDsn = parse_url($endpointUrl);

        if (!is_array($parsedDsn)) {
            throw new InvalidArgumentException('Unable to parse provided DSN');
        }

        if (!isset($parsedDsn['host']) || !isset($parsedDsn['port'])) {
            throw new InvalidArgumentException('Endpoint should have host, port');
        }
        
        $this->jaegerSender = new JaegerSender(
            $name,
            $parsedDsn['host'], 
            $parsedDsn['port'],
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
        return new Exporter(
            $name,
            $endpointUrl
        );
    }
}