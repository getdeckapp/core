<?php

namespace Deck\Core\Contracts;

interface ExposesDeckContext
{
    /**
     * @return array<string, bool|int|float|string|null>
     */
    public function deckContext(): array;
}
