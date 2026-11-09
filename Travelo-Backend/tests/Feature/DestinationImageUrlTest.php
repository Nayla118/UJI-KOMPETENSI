<?php

namespace Tests\Feature;

use App\Models\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The destination `image` field must be an absolute, reachable URL whose host
 * comes from `APP_URL` (via `asset()`/`url()`), never a hardcoded
 * `localhost`/`127.0.0.1` that an emulator or physical device cannot open.
 */
class DestinationImageUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_returns_absolute_image_url_with_app_url_host(): void
    {
        // Simulate a public APP_URL reachable from emulators/physical devices.
        URL::forceRootUrl('https://travelo.example.com');

        $destination = Destination::create([
            'name' => 'Gunung Demo',
            'city' => 'Probolinggo',
            'country' => 'Indonesia',
            'category' => Destination::CATEGORY_GUNUNG,
            'image' => 'destinations/sample.jpg',
        ]);

        $response = $this->getJson("/api/destinations/{$destination->id}");
        $response->assertOk();

        $image = $response->json('data.image');

        $this->assertStringStartsWith('http', $image);
        $this->assertStringContainsString('travelo.example.com/storage/', $image);
        $this->assertStringNotContainsString('localhost', $image);
        $this->assertStringNotContainsString('127.0.0.1', $image);
    }
}
