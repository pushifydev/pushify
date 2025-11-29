<?php

namespace App\Service;

use App\Entity\Deployment;
use App\Entity\Project;
use App\Entity\Server;
use App\Entity\User;
use App\Message\ProcessDeploymentMessage;
use App\Repository\DeploymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use App\Entity\Webhook;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Process\Process;

class DeploymentService
{
    private string $workDir;
    private string $registryUrl;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private DeploymentRepository $deploymentRepository,
        private GitHubService $gitHubService,
        private ServerService $serverService,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private NotificationService $notificationService,
        private WebhookService $webhookService,
        private ActivityLogService $activityLogService,
        private EnvironmentService $environmentService,
        string $projectDir
    ) {
        $this->workDir = $projectDir . '/var/deployments';
        $this->registryUrl = $_ENV['DOCKER_REGISTRY_URL'] ?? 'localhost:5000';

        if (!is_dir($this->workDir)) {
            mkdir($this->workDir, 0755, true);
        }
    }

    /**
     * Create a new deployment and queue it for processing
     */
    public function createDeployment(
        Project $project,
        User $triggeredBy,
        string $trigger = Deployment::TRIGGER_MANUAL,
        ?string $commitHash = null,
        ?string $commitMessage = null
    ): Deployment {
        $deployment = new Deployment();
        $deployment->setProject($project);
        $deployment->setTriggeredBy($triggeredBy);
        $deployment->setTrigger($trigger);
        $deployment->setBranch($project->getBranch());
        $deployment->setCommitHash($commitHash);
        $deployment->setCommitMessage($commitMessage);

        $this->entityManager->persist($deployment);
        $this->entityManager->flush();

        // Log activity
        $this->activityLogService->logDeploymentStarted($deployment, $triggeredBy);

        // Dispatch message to queue
        $this->messageBus->dispatch(new ProcessDeploymentMessage($deployment->getId()));

        return $deployment;
    }

    /**
     * Create a rollback deployment (reuses existing Docker image)
     */
    public function createRollbackDeployment(
        Project $project,
        User $triggeredBy,
        Deployment $targetDeployment
    ): Deployment {
        // Get current production deployment to set as rollbackFrom
        $currentProduction = $this->deploymentRepository->findCurrentProduction($project);

        $deployment = new Deployment();
        $deployment->setProject($project);
        $deployment->setTriggeredBy($triggeredBy);
        $deployment->setTrigger(Deployment::TRIGGER_ROLLBACK);
        $deployment->setBranch($targetDeployment->getBranch());
        $deployment->setCommitHash($targetDeployment->getCommitHash());
        $deployment->setCommitMessage('Rollback to deployment #' . $targetDeployment->getId());
        // Reuse the Docker image from target deployment
        $deployment->setDockerImage($targetDeployment->getDockerImage());
        $deployment->setDockerTag($targetDeployment->getDockerTag());
        // Set rollback reference
        $deployment->setRollbackFrom($currentProduction);

        $this->entityManager->persist($deployment);
        $this->entityManager->flush();

        // Dispatch message to queue - handler will skip build and go directly to deploy
        $this->messageBus->dispatch(new ProcessDeploymentMessage($deployment->getId()));

        return $deployment;
    }

    /**
     * Process a deployment (called by message handler)
     */
    public function processDeployment(Deployment $deployment): void
    {
        $project = $deployment->getProject();
        $deployDir = $this->workDir . '/' . $deployment->getId();
        $isRollback = $deployment->getTrigger() === Deployment::TRIGGER_ROLLBACK;

        try {
            // For rollback deployments, skip build and use existing Docker image
            if ($isRollback && $deployment->getDockerImage() && $deployment->getDockerTag()) {
                $deployment->appendBuildLog("🔄 Rollback deployment - skipping build, using existing image");
                $deployment->appendBuildLog("📦 Image: {$deployment->getDockerImage()}:{$deployment->getDockerTag()}");
                $deployment->setBuildDuration(0);
            } else {
                // Step 1: Clone repository
                $deployment->setStatus(Deployment::STATUS_BUILDING);
                $deployment->setBuildStartedAt(new \DateTimeImmutable());

                $this->logger->info('About to save deployment with BUILDING status');
                try {
                    $this->save($deployment);
                    $this->logger->info('Deployment saved successfully');
                } catch (\Exception $e) {
                    $this->logger->error('FAILED to save deployment!', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }

                // Trigger deployment started webhook
                $this->logger->info('Triggering deployment started webhook');
                try {
                    $this->webhookService->triggerDeployment($deployment, Webhook::EVENT_DEPLOYMENT_STARTED);
                    $this->logger->info('Webhook triggered successfully');
                } catch (\Exception $e) {
                    $this->logger->error('Webhook trigger failed', [
                        'error' => $e->getMessage()
                    ]);
                    // Don't throw - webhooks should not stop deployment
                }

                $this->logger->info('Starting repository clone');
                $this->cloneRepository($deployment, $project, $deployDir);
                $this->logger->info('Repository cloned successfully');

                // Step 2: Generate Dockerfile if not exists
                $this->ensureDockerfile($deployment, $project, $deployDir);

                // Step 3: Build Docker image
                $imageTag = $this->buildDockerImage($deployment, $project, $deployDir);
                $deployment->setDockerImage($this->registryUrl . '/' . $project->getSlug());
                $deployment->setDockerTag($imageTag);

                $deployment->setBuildFinishedAt(new \DateTimeImmutable());
                $deployment->setBuildDuration(
                    $deployment->getBuildFinishedAt()->getTimestamp() - $deployment->getBuildStartedAt()->getTimestamp()
                );
            }

            // Step 4: Deploy to server
            $deployment->setStatus(Deployment::STATUS_DEPLOYING);
            $deployment->setDeployStartedAt(new \DateTimeImmutable());
            $this->save($deployment);

            $this->deployToServer($deployment, $project);

            $deployment->setDeployFinishedAt(new \DateTimeImmutable());
            $deployment->setDeployDuration(
                $deployment->getDeployFinishedAt()->getTimestamp() - $deployment->getDeployStartedAt()->getTimestamp()
            );

            // Success!
            $deployment->setStatus(Deployment::STATUS_SUCCESS);
            $deployment->setDeploymentUrl($project->getProductionUrl());

            // Mark this deployment as current production
            $this->deploymentRepository->clearCurrentProduction($project);
            $deployment->setIsCurrentProduction(true);

            // Update project
            $project->setStatus(Project::STATUS_DEPLOYED);
            $project->setLastDeployedAt(new \DateTimeImmutable());

            $this->save($deployment);
            $this->entityManager->flush();

            // Send success notification
            $this->notificationService->notifyDeploymentSuccess($deployment);

            // Trigger deployment success webhook
            $this->webhookService->triggerDeployment($deployment, Webhook::EVENT_DEPLOYMENT_SUCCESS);

            // Log activity
            if ($isRollback) {
                $this->activityLogService->logRollback($deployment, $deployment->getTriggeredBy());
            } else {
                $this->activityLogService->logDeploymentSuccess($deployment);
            }

        } catch (\Exception $e) {
            $this->logger->error('Exception caught in processDeployment', [
                'error' => $e->getMessage(),
                'type' => get_class($e)
            ]);

            $deployment->setStatus(Deployment::STATUS_FAILED);
            $deployment->setErrorMessage($e->getMessage());
            $deployment->appendBuildLog("\n\n❌ ERROR: " . $e->getMessage());

            $project->setStatus(Project::STATUS_FAILED);

            try {
                $this->save($deployment);  // save() already does flush()
            } catch (\Exception $saveException) {
                $this->logger->critical('Failed to save failed deployment', [
                    'error' => $saveException->getMessage()
                ]);
            }

            // Send failure notification
            $this->notificationService->notifyDeploymentFailed($deployment);

            // Trigger deployment failed webhook
            $this->webhookService->triggerDeployment($deployment, Webhook::EVENT_DEPLOYMENT_FAILED);

            // Log activity
            $this->activityLogService->logDeploymentFailed($deployment);

            $this->logger->error('Deployment failed', [
                'deployment_id' => $deployment->getId(),
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Cleanup
            $this->cleanup($deployDir);
        }
    }

    private function cloneRepository(Deployment $deployment, Project $project, string $deployDir): void
    {
        $deployment->appendBuildLog("📦 Cloning repository...");

        if (!is_dir($deployDir)) {
            mkdir($deployDir, 0755, true);
        }

        $repoUrl = $project->getRepositoryUrl();
        $branch = $project->getBranch() ?? 'main';

        // Add GitHub token for private repos
        $owner = $project->getOwner();
        if ($owner && $owner->getGithubAccessToken()) {
            $repoUrl = str_replace(
                'https://github.com/',
                'https://' . $owner->getGithubAccessToken() . '@github.com/',
                $repoUrl
            );
        }

        $process = new Process([
            'git', 'clone',
            '--depth', '1',
            '--branch', $branch,
            $repoUrl,
            $deployDir
        ]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = $process->getErrorOutput();
            $this->logger->error('Git clone failed', [
                'error' => $error,
                'exit_code' => $process->getExitCode(),
                'repo_url' => $repoUrl,
                'branch' => $branch
            ]);
            throw new \RuntimeException('Failed to clone repository: ' . $error);
        }

        // Get commit info
        $commitProcess = new Process(['git', 'rev-parse', 'HEAD'], $deployDir);
        $commitProcess->run();
        if ($commitProcess->isSuccessful()) {
            $deployment->setCommitHash(trim($commitProcess->getOutput()));
        }

        $messageProcess = new Process(['git', 'log', '-1', '--pretty=%B'], $deployDir);
        $messageProcess->run();
        if ($messageProcess->isSuccessful()) {
            $deployment->setCommitMessage(trim($messageProcess->getOutput()));
        }

        $deployment->appendBuildLog("✓ Repository cloned (commit: " . $deployment->getShortCommitHash() . ")");
        $this->save($deployment);
    }

    private function ensureDockerfile(Deployment $deployment, Project $project, string $deployDir): void
    {
        $rootDir = $deployDir;
        if ($project->getRootDirectory() && $project->getRootDirectory() !== './') {
            $rootDir = $deployDir . '/' . trim($project->getRootDirectory(), '/');
        }

        $dockerfilePath = $rootDir . '/Dockerfile';

        if (file_exists($dockerfilePath)) {
            $deployment->appendBuildLog("✓ Using existing Dockerfile");
            return;
        }

        $deployment->appendBuildLog("📝 Generating Dockerfile for " . $project->getFramework() . "...");

        $dockerfile = $this->generateDockerfile($project);
        file_put_contents($dockerfilePath, $dockerfile);

        $deployment->appendBuildLog("✓ Dockerfile generated");
        $this->save($deployment);
    }

    private function generateDockerfile(Project $project): string
    {
        $framework = $project->getFramework();
        $installCommand = $project->getInstallCommand() ?? 'npm install';
        $buildCommand = $project->getBuildCommand() ?? 'npm run build';
        $startCommand = $project->getStartCommand() ?? 'npm start';
        $outputDir = $project->getOutputDirectory() ?? 'dist';
        // Get environment variables from the new dedicated table
        $envVars = $this->environmentService->getProjectEnvVars($project);

        return match ($framework) {
            'nextjs' => $this->getNextJsDockerfile($installCommand, $buildCommand, $startCommand, $envVars),
            'react', 'vue', 'svelte' => $this->getStaticSiteDockerfile($installCommand, $buildCommand, $outputDir, $envVars),
            'nuxt' => $this->getNuxtDockerfile($installCommand, $buildCommand, $startCommand, $envVars),
            'laravel' => $this->getLaravelDockerfile(),
            'symfony' => $this->getSymfonyDockerfile(),
            'nodejs' => $this->getNodeJsDockerfile($installCommand, $buildCommand, $startCommand, $envVars),
            default => $this->getStaticSiteDockerfile($installCommand, $buildCommand, $outputDir, $envVars),
        };
    }

    private function getNextJsDockerfile(string $installCommand, string $buildCommand, string $startCommand, array $envVars = []): string
    {
        // Environment variables (including NEXT_PUBLIC_*) are passed at runtime via docker run -e flags
        // Not defined in Dockerfile to avoid issues with special characters and multiline values

        // Parse start command for CMD (split by spaces, respecting quoted strings)
        preg_match_all('/"(?:\\\\.|[^\\\\"])*"|\S+/', $startCommand, $matches);
        $cmdParts = array_map(fn($part) => trim($part, '"'), $matches[0]);
        $cmdJson = json_encode($cmdParts);

        return <<<DOCKERFILE
FROM node:20-alpine AS deps
WORKDIR /app
COPY package*.json ./
RUN $installCommand

FROM node:20-alpine AS builder
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
ENV NEXT_TELEMETRY_DISABLED=1
RUN $buildCommand

FROM node:20-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1

RUN addgroup --system --gid 1001 nodejs
RUN adduser --system --uid 1001 nextjs

# Copy built application
COPY --from=builder /app/public ./public
COPY --from=builder /app/.next ./.next
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/package.json ./package.json

USER nextjs
EXPOSE 3000
ENV PORT=3000
CMD $cmdJson
DOCKERFILE;
    }

    private function getStaticSiteDockerfile(string $installCommand, string $buildCommand, string $outputDir, array $envVars = []): string
    {
        // Environment variables are passed at runtime via docker run -e flags
        // Not defined in Dockerfile to avoid issues with special characters and multiline values

        return <<<DOCKERFILE
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN $installCommand
COPY . .
RUN $buildCommand

FROM nginx:alpine
COPY --from=builder /app/$outputDir /usr/share/nginx/html
COPY <<EOF /etc/nginx/conf.d/default.conf
server {
    listen 80;
    root /usr/share/nginx/html;
    index index.html;

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF
EXPOSE 80
CMD ["nginx", "-g", "daemon off;"]
DOCKERFILE;
    }

    private function getNuxtDockerfile(string $installCommand, string $buildCommand, string $startCommand, array $envVars = []): string
    {
        // Environment variables are passed at runtime via docker run -e flags
        // Not defined in Dockerfile to avoid issues with special characters and multiline values

        // Parse start command for CMD
        preg_match_all('/"(?:\\\\.|[^\\\\"])*"|\S+/', $startCommand, $matches);
        $cmdParts = array_map(fn($part) => trim($part, '"'), $matches[0]);
        $cmdJson = json_encode($cmdParts);

        return <<<DOCKERFILE
FROM node:20-alpine AS builder
WORKDIR /app
COPY package*.json ./
RUN $installCommand
COPY . .
RUN $buildCommand

FROM node:20-alpine
WORKDIR /app
COPY --from=builder /app/.output ./
ENV HOST 0.0.0.0
EXPOSE 3000
CMD $cmdJson
DOCKERFILE;
    }

    private function getNodeJsDockerfile(string $installCommand, string $buildCommand, string $startCommand, array $envVars = []): string
    {
        // Environment variables are passed at runtime via docker run -e flags
        // Not defined in Dockerfile to avoid issues with special characters and multiline values

        // Parse start command for CMD
        preg_match_all('/"(?:\\\\.|[^\\\\"])*"|\S+/', $startCommand, $matches);
        $cmdParts = array_map(fn($part) => trim($part, '"'), $matches[0]);
        $cmdJson = json_encode($cmdParts);

        return <<<DOCKERFILE
FROM node:20-alpine

# Create app user for security (non-root)
RUN addgroup -g 1001 -S nodejs && adduser -S nodejs -u 1001

WORKDIR /app

# Install dependencies as root
COPY package*.json ./
RUN $installCommand --production

# Copy application code
COPY . .

# Build if needed
RUN $buildCommand || true

# Change ownership to nodejs user
RUN chown -R nodejs:nodejs /app

# Switch to non-root user
USER nodejs

ENV NODE_ENV production
EXPOSE 3000
CMD $cmdJson
DOCKERFILE;
    }

    private function getLaravelDockerfile(): string
    {
        return <<<'DOCKERFILE'
FROM php:8.2-fpm-alpine AS builder

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    nodejs \
    npm \
    git \
    zip \
    unzip

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Generate optimized autoloader and cache
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Build frontend assets if needed
RUN if [ -f "package.json" ]; then npm install && npm run build; fi

# Runtime stage
FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor
RUN docker-php-ext-install pdo pdo_mysql opcache

WORKDIR /var/www/html

# Copy built application
COPY --from=builder /var/www/html /var/www/html

# Configure Nginx
COPY <<EOF /etc/nginx/http.d/default.conf
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Configure Supervisor
COPY <<EOF /etc/supervisor/conf.d/supervisord.conf
[supervisord]
nodaemon=true
user=root
logfile=/dev/stdout
logfile_maxbytes=0
pidfile=/run/supervisord.pid

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
DOCKERFILE;
    }

    private function getSymfonyDockerfile(): string
    {
        return <<<'DOCKERFILE'
FROM php:8.2-fpm-alpine AS builder

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    nodejs \
    npm \
    git \
    zip \
    unzip \
    icu-dev

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql opcache intl

# Configure OPcache for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Build assets
RUN if [ -f "package.json" ]; then npm install && npm run build; fi

# Clear and warmup cache
RUN APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear --no-warmup && \
    APP_ENV=prod APP_DEBUG=0 php bin/console cache:warmup

# Runtime stage
FROM php:8.2-fpm-alpine

RUN apk add --no-cache nginx supervisor icu-libs
RUN docker-php-ext-install pdo pdo_mysql opcache intl

# Copy OPcache config
COPY --from=builder /usr/local/etc/php/conf.d/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy built application
COPY --from=builder /var/www/html /var/www/html

# Configure Nginx for Symfony
COPY <<EOF /etc/nginx/http.d/default.conf
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri /index.php\$is_args\$args;
    }

    location ~ ^/index\.php(/|$) {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$document_root;
        internal;
    }

    location ~ \.php$ {
        return 404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

# Configure Supervisor
COPY <<EOF /etc/supervisor/conf.d/supervisord.conf
[supervisord]
nodaemon=true
user=root
logfile=/dev/stdout
logfile_maxbytes=0
pidfile=/run/supervisord.pid

[program:php-fpm]
command=php-fpm
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

[program:nginx]
command=nginx -g 'daemon off;'
autostart=true
autorestart=true
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
EOF

# Set permissions
RUN chown -R www-data:www-data /var/www/html/var

EXPOSE 80
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
DOCKERFILE;
    }

    private function buildDockerImage(Deployment $deployment, Project $project, string $deployDir): string
    {
        $deployment->appendBuildLog("\n🔨 Building Docker image...");
        $this->save($deployment);

        $imageTag = date('Ymd-His') . '-' . substr($deployment->getCommitHash() ?? 'latest', 0, 7);
        $imageName = $this->registryUrl . '/' . $project->getSlug() . ':' . $imageTag;

        $rootDir = $deployDir;
        if ($project->getRootDirectory() && $project->getRootDirectory() !== './') {
            $rootDir = $deployDir . '/' . trim($project->getRootDirectory(), '/');
        }

        // Build docker build command with build args for env vars
        $dockerBuildCmd = [
            'docker', 'build',
            '--platform', 'linux/amd64',  // Build for x86_64 servers
            '-t', $imageName,
        ];

        // Note: Environment variables are passed at runtime via docker run -e flags
        // Build args are intentionally not used to avoid issues with special characters
        // and multiline values (like FIREBASE_PRIVATE_KEY)
        $envVars = $this->environmentService->getProjectEnvVars($project);

        if (count($envVars) > 0) {
            $deployment->appendBuildLog("📦 " . count($envVars) . " environment variables will be injected at runtime...");
            $this->save($deployment);
        }

        $dockerBuildCmd[] = '-f';
        $dockerBuildCmd[] = $rootDir . '/Dockerfile';
        $dockerBuildCmd[] = $rootDir;

        $process = new Process($dockerBuildCmd);
        $process->setTimeout(600);

        // Use start() + wait() for real-time streaming
        $process->start();

        $lastSave = microtime(true);
        $buffer = '';

        foreach ($process as $type => $data) {
            $buffer .= $data;

            // Save every 500ms or when we have a complete line
            $now = microtime(true);
            if (($now - $lastSave) > 0.5 || str_contains($data, "\n")) {
                $deployment->appendBuildLog($buffer);
                $this->save($deployment);
                $buffer = '';
                $lastSave = $now;
            }
        }

        // Save any remaining buffer
        if ($buffer) {
            $deployment->appendBuildLog($buffer);
            $this->save($deployment);
        }

        if (!$process->isSuccessful()) {
            throw new \RuntimeException('Docker build failed: ' . $process->getErrorOutput());
        }

        $deployment->appendBuildLog("✓ Docker image built: " . $imageName);
        $this->save($deployment);

        // Skip registry push - we'll transfer directly via SCP for remote deploys
        // or use local image for local deploys

        return $imageTag;
    }

    private function deployToServer(Deployment $deployment, Project $project): void
    {
        // Refresh project to ensure server relationship is loaded
        $this->entityManager->refresh($project);
        $server = $project->getServer();

        $this->logger->info('Deploy to server check', [
            'project_id' => $project->getId(),
            'has_server' => $server !== null,
            'server_status' => $server?->getStatus(),
            'is_active' => $server?->isActive(),
        ]);

        if ($server && $server->isActive()) {
            $this->deployToRemoteServer($deployment, $project, $server);
        } else {
            $this->deployLocally($deployment, $project);
        }
    }

    private function deployToRemoteServer(Deployment $deployment, Project $project, Server $server): void
    {
        $deployment->appendDeployLog("🚀 Deploying to server: " . $server->getName() . " (" . $server->getIpAddress() . ")");
        $this->save($deployment);

        $containerName = 'pushify-' . $project->getSlug();
        $imageName = $project->getSlug() . ':' . $deployment->getDockerTag();
        $localImageName = $deployment->getDockerImage() . ':' . $deployment->getDockerTag();

        // Step 1: Export image to tar
        $deployment->appendDeployLog("📦 Exporting Docker image...");
        $this->save($deployment);

        $tarFile = $this->workDir . '/' . $deployment->getId() . '-image.tar';
        $saveProcess = new Process(['docker', 'save', '-o', $tarFile, $localImageName]);
        $saveProcess->setTimeout(300);
        $saveProcess->run();

        if (!$saveProcess->isSuccessful()) {
            throw new \RuntimeException('Failed to export Docker image: ' . $saveProcess->getErrorOutput());
        }

        $deployment->appendDeployLog("✓ Image exported");
        $this->save($deployment);

        // Step 2: Transfer image to server via SCP
        $deployment->appendDeployLog("📤 Transferring image to server...");
        $this->save($deployment);

        $keyFile = $this->createTempKeyFile($server);
        $remoteImagePath = '/tmp/' . $containerName . '-image.tar';

        $scpProcess = new Process([
            'scp',
            '-i', $keyFile,
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-P', (string) $server->getSshPort(),
            $tarFile,
            $server->getSshUser() . '@' . $server->getIpAddress() . ':' . $remoteImagePath
        ]);
        $scpProcess->setTimeout(600);
        $scpProcess->run();

        // Cleanup local tar
        @unlink($tarFile);

        if (!$scpProcess->isSuccessful()) {
            @unlink($keyFile);
            throw new \RuntimeException('Failed to transfer image: ' . $scpProcess->getErrorOutput());
        }

        $deployment->appendDeployLog("✓ Image transferred");
        $this->save($deployment);

        // Step 3: Load image on remote server
        $deployment->appendDeployLog("📥 Loading image on server...");
        $this->save($deployment);

        $loadResult = $this->executeRemoteCommand($server, $keyFile, "docker load -i {$remoteImagePath} && rm {$remoteImagePath}");
        if (!$loadResult['success']) {
            @unlink($keyFile);
            throw new \RuntimeException('Failed to load image on server: ' . $loadResult['error']);
        }

        $deployment->appendDeployLog("✓ Image loaded on server");
        $this->save($deployment);

        // Step 4: Stop old container
        $this->executeRemoteCommand($server, $keyFile, "docker stop {$containerName} 2>/dev/null || true");
        $this->executeRemoteCommand($server, $keyFile, "docker rm {$containerName} 2>/dev/null || true");

        // Build docker run command with environment variables
        $envVars = $this->environmentService->getProjectEnvVars($project);
        $envString = '';
        foreach ($envVars as $key => $value) {
            // Escape value for shell
            $escapedValue = escapeshellarg($value);
            $envString .= " -e {$key}={$escapedValue}";
        }

        // Step 5: Assign port - use user's PORT env var if available, otherwise auto-assign
        $userPort = isset($envVars['PORT']) ? (int)$envVars['PORT'] : null;
        $port = $project->getContainerPort() ?? $userPort ?? (3000 + $project->getId());
        $project->setContainerPort($port);

        // Step 6: Start new container
        $deployment->appendDeployLog("🚀 Starting container...");
        $deployment->appendDeployLog("📦 Environment variables: " . count($envVars) . " configured");
        $this->save($deployment);

        // Use same port for both external and internal (passthrough mode)
        $runCommand = "docker run -d --name {$containerName} -p {$port}:{$port} --restart unless-stopped{$envString} {$localImageName}";

        $runResult = $this->executeRemoteCommand($server, $keyFile, $runCommand);

        @unlink($keyFile);

        if (!$runResult['success']) {
            throw new \RuntimeException('Failed to start container: ' . $runResult['error']);
        }

        $containerId = trim($runResult['output']);
        $project->setContainerId(substr($containerId, 0, 12));

        $deployment->appendDeployLog("✓ Container started: " . $project->getContainerId());
        $deployment->appendDeployLog("✓ Application available at: http://" . $server->getIpAddress() . ":" . $port);
        $deployment->setDeploymentUrl('http://' . $server->getIpAddress() . ':' . $port);

        $project->setProductionUrl('http://' . $server->getIpAddress() . ':' . $port);

        $this->save($deployment);
    }

    private function deployLocally(Deployment $deployment, Project $project): void
    {
        $deployment->appendDeployLog("🚀 Deploying locally (no server assigned)...");
        $this->save($deployment);

        $containerName = 'pushify-' . $project->getSlug();
        $imageName = $deployment->getDockerImage() . ':' . $deployment->getDockerTag();

        // Stop existing container
        $stopProcess = new Process(['docker', 'stop', $containerName]);
        $stopProcess->run();

        $rmProcess = new Process(['docker', 'rm', $containerName]);
        $rmProcess->run();

        // Start new container
        $port = $project->getContainerPort() ?? (3000 + $project->getId());
        $project->setContainerPort($port);

        // Build docker run command with environment variables
        $dockerRunCmd = [
            'docker', 'run', '-d',
            '--name', $containerName,
            '-p', $port . ':3000',
            '--restart', 'unless-stopped',
        ];

        // Add environment variables
        $envVars = $this->environmentService->getProjectEnvVars($project);
        foreach ($envVars as $key => $value) {
            $dockerRunCmd[] = '-e';
            $dockerRunCmd[] = "{$key}={$value}";
        }

        // Add image name at the end
        $dockerRunCmd[] = $imageName;

        $deployment->appendDeployLog("📦 Environment variables: " . count($envVars) . " configured");
        $this->save($deployment);

        $runProcess = new Process($dockerRunCmd);
        $runProcess->setTimeout(60);
        $runProcess->run();

        if (!$runProcess->isSuccessful()) {
            throw new \RuntimeException('Failed to start container: ' . $runProcess->getErrorOutput());
        }

        // Get container ID
        $containerId = trim($runProcess->getOutput());
        $project->setContainerId(substr($containerId, 0, 12));

        $deployment->appendDeployLog("✓ Container started: " . $project->getContainerId());
        $deployment->appendDeployLog("✓ Application available at port: " . $port);

        $baseUrl = $_ENV['DEFAULT_URI'] ?? 'http://localhost:' . $port;
        $deployment->setDeploymentUrl($baseUrl);
        $project->setProductionUrl($baseUrl);

        $this->save($deployment);
    }

    private function executeRemoteCommand(Server $server, string $keyFile, string $command): array
    {
        $process = new Process([
            'ssh',
            '-i', $keyFile,
            '-o', 'StrictHostKeyChecking=no',
            '-o', 'UserKnownHostsFile=/dev/null',
            '-o', 'ConnectTimeout=30',
            '-p', (string) $server->getSshPort(),
            $server->getSshUser() . '@' . $server->getIpAddress(),
            $command
        ]);
        $process->setTimeout(300);
        $process->run();

        return [
            'success' => $process->isSuccessful(),
            'output' => $process->getOutput(),
            'error' => $process->getErrorOutput(),
        ];
    }

    private function createTempKeyFile(Server $server): string
    {
        $keyFile = sys_get_temp_dir() . '/pushify_deploy_' . uniqid() . '.key';
        file_put_contents($keyFile, $server->getSshPrivateKey());
        chmod($keyFile, 0600);
        return $keyFile;
    }

    private function cleanup(string $deployDir): void
    {
        if (is_dir($deployDir)) {
            $process = new Process(['rm', '-rf', $deployDir]);
            $process->run();
        }
    }

    private function save(Deployment $deployment): void
    {
        $this->entityManager->persist($deployment);
        $this->entityManager->flush();
    }
}
