<?php

namespace GeneaLabs\LaravelSocialiter;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;

class Socialiter
{
    public static $runsMigrations = true;

    protected $driver;
    protected $apiToken;

    public static function ignoreMigrations()
    {
        static::$runsMigrations = false;
    }

    public function driver(string $driver) : self
    {
        $this->driver = $driver;

        return $this;
    }

    public function login() : Model
    {
        $socialiteUser = Socialite::driver($this->driver)
            ->user();

        return $this->performLogin($socialiteUser);
    }

    public function apiLogin(AbstractUser $socialiteUser, string $apiToken) : Model
    {
        $this->apiToken = $apiToken;

        return $this->performLogin($socialiteUser);
    }

    protected function performLogin(AbstractUser $socialiteUser) : Model
    {
        $user = $this
            ->getUser($socialiteUser, $this->driver);
        $user->load("socialCredentials");

        auth()->login($user);

        return $user;
    }

    protected function getUser(AbstractUser $socialiteUser) : Model
    {
        return $this
            ->createCredentials($socialiteUser)
            ->user;
    }

    protected function createUser(AbstractUser $socialiteUser) : Model
    {
        $userClass = config("auth.providers.users.model");
        $user = (new $userClass)
            ->firstOrNew([
                "email" => $socialiteUser->getEmail(),
                "api_token" => $this->apiToken
            ])
            ->fill([
                "name" => $socialiteUser->getName(),
                "password" => Str::random(64),
            ]);
        $user->save();

        return $user;
    }

    protected function createCredentials(AbstractUser $socialiteUser) : SocialCredentials
    {
        $credentialsModel = SocialCredentials::model();
        $socialiteCredentials = (new $credentialsModel)
            ->with("user")
            ->firstOrNew([
                "provider_id" => $socialiteUser->getId(),
                "provider_name" => $this->driver,
            ])
            ->fill([
                "access_token" => $socialiteUser->token,
                "avatar" => $socialiteUser->getAvatar(),
                "email" => $socialiteUser->getEmail(),
                "expires_at" => (new Carbon)->now()->addSeconds($socialiteUser->expiresIn),
                "name" => $socialiteUser->getName(),
                "nickname" => $socialiteUser->getNickname(),
                "provider_id" => $socialiteUser->getId(),
                "provider_name" => $this->driver,
                "refresh_token" => $socialiteUser->refreshToken,
            ]);

        if (! $socialiteCredentials->exists) {
            $user = $this->createUser($socialiteUser);
            $socialiteCredentials->user()->associate($user);
        }

        $socialiteCredentials->save();

        return $socialiteCredentials;
    }
}
