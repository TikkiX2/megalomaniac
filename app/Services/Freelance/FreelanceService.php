<?php

namespace App\Services\Freelance;

use App\Models\Client;
use App\Models\Project;
use App\Models\Quote;
use App\Models\User;
use App\Services\Projects\ProjectService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FreelanceService
{
    public function __construct(protected ProjectService $projects) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createClient(User $user, array $data): Client
    {
        if (empty($data['name'])) {
            throw new InvalidArgumentException('The name field is required.');
        }

        return Client::create([
            ...$data,
            'user_id' => $user->id,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateClient(User $user, Client $client, array $data): Client
    {
        $this->assertOwner($user, $client);

        $client->update($data);

        return $client->fresh();
    }

    public function deleteClient(User $user, Client $client): void
    {
        $this->assertOwner($user, $client);

        $client->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createQuote(User $user, array $data): Quote
    {
        if (empty($data['client_id']) || empty($data['currency_id']) || empty($data['issue_date'])) {
            throw new InvalidArgumentException('Client, currency and issue date are required.');
        }

        $client = Client::where('user_id', $user->id)->find($data['client_id']);

        if (! $client) {
            throw new AuthorizationException('You do not own this client.');
        }

        $items = (array) ($data['items'] ?? []);
        unset($data['items']);

        return DB::transaction(function () use ($user, $data, $items): Quote {
            $subtotal = collect($items)->sum(fn ($item) => (float) ($item['subtotal'] ?? 0));

            $quote = Quote::create([
                ...$data,
                'user_id' => $user->id,
                'client_id' => $data['client_id'],
                'quote_number' => Quote::generateQuoteNumber(),
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'status' => $data['status'] ?? 'draft',
            ]);

            $this->syncItems($quote, $items);

            return $quote->fresh(['items']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateQuote(User $user, Quote $quote, array $data): Quote
    {
        $this->assertOwner($user, $quote);

        if (array_key_exists('client_id', $data) && $data['client_id']) {
            $client = Client::where('user_id', $user->id)->find($data['client_id']);

            if (! $client) {
                throw new AuthorizationException('You do not own this client.');
            }
        }

        $items = null;

        if (array_key_exists('items', $data)) {
            $items = (array) $data['items'];
            unset($data['items']);
        }

        return DB::transaction(function () use ($quote, $data, $items): Quote {
            if ($items !== null) {
                $subtotal = collect($items)->sum(fn ($item) => (float) ($item['subtotal'] ?? 0));
                $data['subtotal'] = $subtotal;
                $data['total'] = $subtotal;
            }

            $quote->update($data);

            if ($items !== null) {
                $quote->items()->delete();
                $this->syncItems($quote, $items);
            }

            return $quote->fresh(['items']);
        });
    }

    public function deleteQuote(User $user, Quote $quote): void
    {
        $this->assertOwner($user, $quote);

        $quote->delete();
    }

    public function convertQuoteToProject(User $user, Quote $quote): Project
    {
        $this->assertOwner($user, $quote);

        return DB::transaction(function () use ($user, $quote): Project {
            $project = $this->projects->create($user, [
                'client_id' => $quote->client_id,
                'name' => $quote->title ?? 'Proyecto de '.$quote->quote_number,
                'type' => 'freelance',
                'status' => 'pending',
                'currency_id' => $quote->currency_id,
                'total_amount' => $quote->total,
                'hourly_rate' => $quote->hourly_rate,
            ]);

            $quote->update([
                'project_id' => $project->id,
                'status' => 'accepted',
            ]);

            return $project;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(Quote $quote, array $items): void
    {
        foreach ($items as $index => $item) {
            $quote->items()->create([
                'description' => $item['description'] ?? 'Item',
                'hours' => $item['hours'] ?? 0,
                'hourly_rate' => $item['hourly_rate'] ?? 0,
                'subtotal' => $item['subtotal'] ?? 0,
                'order' => $item['order'] ?? $index,
            ]);
        }
    }

    private function assertOwner(User $user, Client|Quote $model): void
    {
        if ($model->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this record.');
        }
    }
}
