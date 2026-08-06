<?php

namespace Deck\Core\Bus;

use Deck\Core\Blocking\InterceptBlockedDispatch;
use Deck\Core\Support\DeckResilience;
use Illuminate\Bus\Dispatcher;

class DeckDispatcher extends Dispatcher
{
    /**
     * @param  mixed  $command
     * @return mixed
     */
    public function dispatchToQueue($command)
    {
        if (DeckResilience::runSilently(
            fn (): bool => InterceptBlockedDispatch::intercept($command),
            false,
        )) {
            return null;
        }

        return parent::dispatchToQueue($command);
    }
}
