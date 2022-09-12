<?php

declare(strict_types=1);

class SpanContextShim implements \OpenTracing\SpanContext
{
    /**
     * Returns the value of a baggage item based on its key. If there is no
     * value with such key it will return null.
     *
     * @param string $key
     * @return string|null
     */
    public function getBaggageItem(string $key): ?string
    {
        throw new BadMethodCallException("Not implemented");
    }

    /**
     * Creates a new SpanContext out of the existing one and the new key => value pair.
     *
     * @param string $key
     * @param string $value
     * @return SpanContext
     */
    public function withBaggageItem(string $key, string $value): \OpenTracing\SpanContext
    {
        throw new BadMethodCallException("Not implemented");
    }

    public function getIterator(): Traversable
    {
        throw new BadMethodCallException("Not implemented");
    }
}
