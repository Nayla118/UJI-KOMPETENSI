<?php

namespace Tests\Feature;

use App\Contracts\FirebaseIdentityVerifier;
use App\Models\User;
use App\Services\FirebaseAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'firebase_uid' => 'photo-uid-'.uniqid(),
            'name' => 'Photo Tester',
            'email' => 'photo-'.uniqid().'@example.com',
        ], $overrides));
    }

    public function test_guest_cannot_upload_a_profile_photo(): void
    {
        Storage::fake('public');

        $this->post('/api/auth/profile/photo', [
            'photo' => UploadedFile::fake()->image('avatar.jpg', 100, 100),
        ])->assertUnauthorized();
    }

    public function test_traveller_can_upload_and_replace_a_profile_photo(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        Sanctum::actingAs($user);

        $first = $this->post('/api/auth/profile/photo', [
            'photo' => UploadedFile::fake()->image('first.jpg', 640, 640),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Profile photo updated');

        $firstPath = $user->fresh()->getRawOriginal('photo');

        $this->assertIsString($firstPath);
        $this->assertStringStartsWith('profile-photos/', $firstPath);
        $this->assertStringContainsString($firstPath, $first->json('data.photo'));
        Storage::disk('public')->assertExists($firstPath);

        $this->post('/api/auth/profile/photo', [
            'photo' => UploadedFile::fake()->image('second.png', 512, 512),
        ])->assertOk();

        $secondPath = $user->fresh()->getRawOriginal('photo');

        $this->assertNotSame($firstPath, $secondPath);
        $this->assertStringEndsWith('.png', $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_profile_photo_must_be_a_supported_raster_image(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        Sanctum::actingAs($user);

        $this->post('/api/auth/profile/photo', [
            'photo' => UploadedFile::fake()->create('avatar.pdf', 20, 'application/pdf'),
        ])->assertStatus(422);

        $this->post('/api/auth/profile/photo', [
            'photo' => UploadedFile::fake()->create('avatar.svg', 20, 'image/svg+xml'),
        ])->assertStatus(422);

        $this->post('/api/auth/profile/photo', [
            'photo' => UploadedFile::fake()->image('too-large.jpg', 800, 800)->size(11000),
        ])->assertStatus(422);

        $this->assertNull($user->fresh()->getRawOriginal('photo'));
    }

    public function test_custom_photo_survives_a_later_firebase_sign_in(): void
    {
        $user = $this->makeUser([
            'photo' => 'profile-photos/1/custom.jpg',
        ]);

        $service = new FirebaseAuthService(new class implements FirebaseIdentityVerifier
        {
            public function verify(string $idToken): array
            {
                return [];
            }
        });

        $result = $service->syncUser(
            $user->firebase_uid,
            $user->email,
            'Provider Name',
            'https://provider.example/photo.jpg'
        );

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame(
            $user->getRawOriginal('photo'),
            $result['user']->getRawOriginal('photo')
        );
    }
}
