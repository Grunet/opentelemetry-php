<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Jaeger;

use OpenTelemetry\SDK\Trace\SpanConverterInterface;
use OpenTelemetry\SDK\Trace\SpanDataInterface;

class SpanConverter implements SpanConverterInterface
{
    private string $serviceName;

    public function __construct(string $serviceName)
    {
        $this->serviceName = $serviceName;
    }

    public function convert(iterable $spans): array
    {
        $aggregate = [];
        foreach ($spans as $span) {
            $aggregate[] = $this->convertSpan($span);
        }

        return $aggregate;
    }

    private function convertSpan(SpanDataInterface $span): array
    {
        return [];
    }
}
