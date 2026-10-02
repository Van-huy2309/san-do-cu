<?php

namespace App\Auth;

use Firebase\JWT\JWT;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;
use UnexpectedValueException;

class AppleProvider extends AbstractProvider
{
    protected $encodingType = PHP_QUERY_RFC3986;

    protected $scopes = ['name', 'email'];

    protected $scopeSeparator = ' ';

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase('https://appleid.apple.com/auth/authorize', $state);
    }

    protected function getTokenUrl(): string
    {
        return 'https://appleid.apple.com/auth/token';
    }

    protected function getCodeFields($state = null): array
    {
        $fields = parent::getCodeFields($state);
        $fields['response_mode'] = 'form_post';

        return $fields;
    }

    public function getAccessTokenResponse($code): array
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->getClientSecret())],
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => $this->clientId,
                'client_secret' => $this->getClientSecret(),
                'code' => $code,
                'redirect_uri' => $this->redirectUrl,
            ],
        ]);

        return json_decode((string) $response->getBody(), true) ?: [];
    }

    protected function getUserByToken($token): array
    {
        $parts = explode('.', $token);
        if (count($parts) < 2) {
            throw new UnexpectedValueException('Apple id_token không hợp lệ.');
        }

        return json_decode($this->base64UrlDecode($parts[1]), true) ?: [];
    }

    protected function mapUserToObject(array $user): User
    {
        $name = data_get(request()->input('user'), 'name');
        $full = is_array($name)
            ? trim(($name['firstName'] ?? '') . ' ' . ($name['lastName'] ?? ''))
            : null;

        return (new User)->setRaw($user)->map([
            'id' => $user['sub'] ?? null,
            'nickname' => null,
            'name' => $full ?: ($user['email'] ?? 'Apple User'),
            'email' => $user['email'] ?? null,
            'avatar' => null,
        ]);
    }

    private function getClientSecret(): string
    {
        if ($cached = config('services.apple.client_secret')) {
            return $cached;
        }

        $key = (string) config('services.apple.private_key');
        $key = str_contains($key, 'BEGIN') ? $key : "-----BEGIN PRIVATE KEY-----\n" . trim($key) . "\n-----END PRIVATE KEY-----";
        $payload = [
            'iss' => config('services.apple.team_id'),
            'iat' => time(),
            'exp' => time() + 86400 * 180,
            'aud' => 'https://appleid.apple.com',
            'sub' => $this->clientId,
        ];

        return JWT::encode($payload, $key, 'ES256', config('services.apple.key_id'));
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
