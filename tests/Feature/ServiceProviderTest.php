<?php

use Pijler\Actions\ServiceProvider;

test('it should register the service provider', function () {
    expect(app()->getProvider(ServiceProvider::class))->toBeInstanceOf(ServiceProvider::class);
});
