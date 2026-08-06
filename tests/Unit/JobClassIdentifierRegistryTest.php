<?php

use Deck\Core\Blocking\JobClassBlock;
use Deck\Core\Blocking\JobClassIdentifierRegistry;
use Deck\Core\Tests\Fixtures\SuccessfulTestJob;

it('blocks linked display names and fqcn together', function () {
    JobClassIdentifierRegistry::link('display-only-name', SuccessfulTestJob::class);

    JobClassBlock::block('display-only-name');

    expect(JobClassBlock::isBlocked(SuccessfulTestJob::class))->toBeTrue()
        ->and(JobClassBlock::isBlocked('display-only-name'))->toBeTrue();
});
