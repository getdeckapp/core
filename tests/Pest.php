<?php

use Deck\Core\Support\DeckInstallation;
use Deck\Core\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

function deckProject(): string
{
    return DeckInstallation::project();
}

function deckEnvironment(): string
{
    return DeckInstallation::environment();
}
