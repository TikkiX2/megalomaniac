<?php

namespace Database\Seeders;

use App\Models\Person;
use App\Models\PersonInteraction;
use App\Models\PersonKeyDate;
use App\Models\PersonSocial;
use App\Models\User;
use App\People\Enums\Closeness;
use App\People\Enums\InteractionChannel;
use App\People\Enums\KeyDateType;
use Illuminate\Database\Seeder;

class PeopleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'test@example.com')->first()
            ?? User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $ana = Person::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'Ana',
            'last_name' => 'Gómez',
            'nickname' => 'Anita',
            'closeness' => Closeness::InnerCircle,
            'is_favorite' => true,
            'birthday' => now()->subYears(29)->startOfYear()->addMonths(9)->addDays(12),
        ]);

        PersonSocial::factory()->create([
            'person_id' => $ana->id,
            'network' => 'instagram',
            'handle' => '@anagomez',
        ]);

        PersonKeyDate::factory()->create([
            'user_id' => $user->id,
            'person_id' => $ana->id,
            'type' => KeyDateType::Anniversary,
            'label' => 'Aniversario de amistad',
            'date' => now()->subYears(6)->startOfYear()->addMonths(10)->addDays(2),
            'is_recurring_annually' => true,
        ]);

        PersonInteraction::factory()->create([
            'user_id' => $user->id,
            'person_id' => $ana->id,
            'channel' => InteractionChannel::Call,
            'occurred_at' => now()->subDays(4),
            'title' => 'Llamada de cumpleaños',
        ]);

        $bruno = Person::factory()->create([
            'user_id' => $user->id,
            'first_name' => 'Bruno',
            'last_name' => 'Díaz',
            'closeness' => Closeness::Friend,
        ]);

        PersonInteraction::factory()->create([
            'user_id' => $user->id,
            'person_id' => $bruno->id,
            'channel' => InteractionChannel::Message,
            'occurred_at' => now()->subDays(45),
            'title' => 'Nos escribimos',
        ]);

        Person::factory()->count(2)->create([
            'user_id' => $user->id,
            'closeness' => Closeness::Acquaintance,
        ]);
    }
}
