<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('quote_items keeps a foreign key to quotes after migrating', function () {
    if (DB::getDriverName() !== 'sqlite') {
        $this->markTestSkipped('PRAGMA introspection is sqlite-only.');
    }

    $foreignKeys = collect(DB::select("PRAGMA foreign_key_list('quote_items')"));

    expect($foreignKeys->contains(
        fn (object $foreignKey): bool => $foreignKey->from === 'quote_id' && $foreignKey->table === 'quotes',
    ))->toBeTrue();
});
