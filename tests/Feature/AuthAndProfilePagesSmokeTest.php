<?php

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders register page without error', function () {
    $response = $this->get('/user/register');

    $response->assertStatus(200);

    $html = $response->getContent();

    $this->assertStringContainsString('first_name', $html, 'first name field missing');
    $this->assertStringContainsString('mid_name', $html, 'mid name field missing');
    $this->assertStringContainsString('last_name', $html, 'last name field missing');
    $this->assertStringContainsString('face_scan_photo', $html, 'face scan upload missing');
    $this->assertStringContainsString('selfie_photo', $html, 'selfie upload missing');
    $this->assertStringContainsString('ktp_photo', $html, 'id photo upload missing');
    $this->assertStringContainsString('avata', $html, 'avatar upload missing');
    $this->assertStringNotContainsString('fi-fo-wizard', $html, 'wizard still rendered');
});

it('renders complete profile page without error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/user/complete-profile');

    $response->assertStatus(200);

    $html = $response->getContent();

    $this->assertStringContainsString('face_scan_photo', $html, 'face scan upload missing');
    $this->assertStringContainsString('selfie_photo', $html, 'selfie upload missing');
    $this->assertStringContainsString('ktp_photo', $html, 'id photo upload missing');
    $this->assertStringContainsString('source_of_funds', $html, 'source of funds missing');
    $this->assertStringContainsString('income_range', $html, 'income range missing');
});
