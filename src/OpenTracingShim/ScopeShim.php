<?php

declare(strict_types=1);

class ScopeShim implements \OpenTracing\Scope
{
    /**
     * Mark the end of the active period for the current thread and {@link Scope},
     * updating the {@link ScopeManager#active()} in the process.
     *
     * NOTE: Calling {@link #close} more than once on a single {@link Scope} instance leads to undefined
     * behavior.
     *
     * @return void
     */
    public function close(): void
    {
        throw new BadMethodCallException("Not implemented");
    }

    /**
     * @return Span the {@link Span} that's been scoped by this {@link Scope}
     */
    public function getSpan(): \OpenTracing\Span
    {
        throw new BadMethodCallException("Not implemented");
    }
}
