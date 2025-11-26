<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GitHubService
{
    private const API_URL = 'https://api.github.com';

    public function __construct(
        private HttpClientInterface $httpClient
    ) {
    }

    /**
     * Get user's repositories from GitHub (with pagination)
     * @return array<array{id: int, name: string, full_name: string, description: ?string, html_url: string, clone_url: string, default_branch: string, private: bool, updated_at: string}>
     */
    public function getUserRepositories(User $user, int $perPage = 200, int $maxPages = 10): array
    {
        $token = $user->getGithubAccessToken();

        if (!$token) {
            return [];
        }

        $allRepos = [];

        try {
            for ($page = 1; $page <= $maxPages; $page++) {
                $response = $this->httpClient->request('GET', self::API_URL . '/user/repos', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/vnd.github.v3+json',
                    ],
                    'query' => [
                        'per_page' => $perPage,
                        'page' => $page,
                        'sort' => 'updated',
                        'direction' => 'desc',
                        'affiliation' => 'owner,collaborator,organization_member', // Get all repos user has access to
                    ],
                ]);

                $repos = $response->toArray();

                if (empty($repos)) {
                    break; // No more repos
                }

                $allRepos = array_merge($allRepos, $repos);

                // If we got less than perPage, we've reached the end
                if (count($repos) < $perPage) {
                    break;
                }
            }

            return $allRepos;
        } catch (\Exception $e) {
            return $allRepos; // Return what we have so far
        }
    }

    /**
     * Get repository details
     */
    public function getRepository(User $user, string $owner, string $repo): ?array
    {
        $token = $user->getGithubAccessToken();

        if (!$token) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', self::API_URL . "/repos/{$owner}/{$repo}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/vnd.github.v3+json',
                ],
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get repository branches
     * @return array<array{name: string}>
     */
    public function getRepositoryBranches(User $user, string $owner, string $repo): array
    {
        $token = $user->getGithubAccessToken();

        if (!$token) {
            return [];
        }

        try {
            $response = $this->httpClient->request('GET', self::API_URL . "/repos/{$owner}/{$repo}/branches", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/vnd.github.v3+json',
                ],
            ]);

            return $response->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Detect framework from repository files
     */
    public function detectFramework(User $user, string $owner, string $repo): ?string
    {
        $token = $user->getGithubAccessToken();

        if (!$token) {
            return null;
        }

        try {
            // Get repository contents
            $response = $this->httpClient->request('GET', self::API_URL . "/repos/{$owner}/{$repo}/contents", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/vnd.github.v3+json',
                ],
            ]);

            $contents = $response->toArray();
            $files = array_column($contents, 'name');

            // Check for framework indicators
            if (in_array('next.config.js', $files) || in_array('next.config.mjs', $files) || in_array('next.config.ts', $files)) {
                return 'nextjs';
            }
            if (in_array('nuxt.config.js', $files) || in_array('nuxt.config.ts', $files)) {
                return 'nuxt';
            }
            if (in_array('svelte.config.js', $files)) {
                return 'svelte';
            }
            if (in_array('artisan', $files) && in_array('composer.json', $files)) {
                return 'laravel';
            }
            if (in_array('symfony.lock', $files) || (in_array('composer.json', $files) && in_array('bin', $files))) {
                return 'symfony';
            }
            if (in_array('vue.config.js', $files) || in_array('vite.config.ts', $files)) {
                // Check package.json for Vue
                return 'vue';
            }
            if (in_array('package.json', $files)) {
                // Generic Node.js/React
                return 'nodejs';
            }
            if (in_array('index.html', $files)) {
                return 'static';
            }

            return 'other';
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get default build settings for framework
     */
    public static function getFrameworkDefaults(string $framework): array
    {
        return match ($framework) {
            'nextjs' => [
                'installCommand' => 'npm install',
                'buildCommand' => 'npm run build',
                'outputDirectory' => '.next',
            ],
            'react' => [
                'installCommand' => 'npm install',
                'buildCommand' => 'npm run build',
                'outputDirectory' => 'build',
            ],
            'vue' => [
                'installCommand' => 'npm install',
                'buildCommand' => 'npm run build',
                'outputDirectory' => 'dist',
            ],
            'nuxt' => [
                'installCommand' => 'npm install',
                'buildCommand' => 'npm run build',
                'outputDirectory' => '.output',
            ],
            'svelte' => [
                'installCommand' => 'npm install',
                'buildCommand' => 'npm run build',
                'outputDirectory' => 'build',
            ],
            'laravel' => [
                'installCommand' => 'composer install --no-dev --optimize-autoloader && php artisan key:generate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache',
                'buildCommand' => 'npm install && npm run build',
                'outputDirectory' => 'public',
            ],
            'symfony' => [
                'installCommand' => 'composer install --no-dev --optimize-autoloader && php bin/console cache:clear --env=prod && php bin/console cache:warmup --env=prod',
                'buildCommand' => 'npm install && npm run build',
                'outputDirectory' => 'public',
            ],
            'nodejs' => [
                'installCommand' => 'npm install',
                'buildCommand' => 'npm run build',
                'outputDirectory' => 'dist',
            ],
            'static' => [
                'installCommand' => '',
                'buildCommand' => '',
                'outputDirectory' => '.',
            ],
            default => [
                'installCommand' => 'npm install',
                'buildCommand' => 'npm run build',
                'outputDirectory' => 'dist',
            ],
        };
    }
}
