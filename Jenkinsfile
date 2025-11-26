pipeline {
    agent any

    environment {
        // Docker Registry (Docker Hub veya private registry)
        DOCKER_REGISTRY = 'docker.io'
        DOCKER_IMAGE = 'pushifydev/pushify'

        // Deployment server
        PRODUCTION_HOST = 'pushify.dev'
        PRODUCTION_USER = 'deploy'

        // Application
        APP_ENV = 'prod'
        APP_NAME = 'pushify'

        // Version from VERSION file
        APP_VERSION = readFile('VERSION').trim()
    }

    stages {
        stage('Checkout') {
            steps {
                echo '📦 Checking out code...'
                checkout scm
                sh 'git fetch --tags'
            }
        }

        stage('Environment Setup') {
            steps {
                echo '🔧 Setting up environment...'
                sh '''
                    echo "Building version: ${APP_VERSION}"
                    echo "Branch: ${GIT_BRANCH}"
                    echo "Commit: ${GIT_COMMIT}"
                '''
            }
        }

        stage('Install Dependencies') {
            parallel {
                stage('PHP Dependencies') {
                    steps {
                        echo '📦 Installing PHP dependencies...'
                        sh 'composer install --no-dev --optimize-autoloader --no-interaction'
                    }
                }
                stage('Node Dependencies') {
                    steps {
                        echo '📦 Installing Node dependencies...'
                        sh 'npm ci --production=false'
                    }
                }
            }
        }

        stage('Build Assets') {
            steps {
                echo '🏗️ Building frontend assets...'
                sh 'npm run build'
            }
        }

        stage('Run Tests') {
            parallel {
                stage('PHP Tests') {
                    steps {
                        echo '🧪 Running PHP tests...'
                        sh 'php bin/phpunit || true'
                    }
                }
                stage('Security Check') {
                    steps {
                        echo '🔒 Running security checks...'
                        sh 'composer audit || true'
                        sh 'npm audit --production || true'
                    }
                }
            }
        }

        stage('Build Docker Image') {
            steps {
                echo '🐳 Building Docker image...'
                script {
                    // Build with version tag
                    def imageTag = "${DOCKER_REGISTRY}/${DOCKER_IMAGE}:${APP_VERSION}"
                    def latestTag = "${DOCKER_REGISTRY}/${DOCKER_IMAGE}:latest"

                    sh """
                        docker build \
                            --build-arg APP_VERSION=${APP_VERSION} \
                            --build-arg BUILD_DATE=\$(date -u +'%Y-%m-%dT%H:%M:%SZ') \
                            --build-arg VCS_REF=${GIT_COMMIT} \
                            -t ${imageTag} \
                            -t ${latestTag} \
                            -f docker/Dockerfile.prod .
                    """
                }
            }
        }

        stage('Push Docker Image') {
            when {
                anyOf {
                    branch 'master'
                    branch 'beta'
                    tag pattern: "v\\d+\\.\\d+\\.\\d+.*", comparator: "REGEXP"
                }
            }
            steps {
                echo '📤 Pushing Docker image to registry...'
                script {
                    docker.withRegistry("https://${DOCKER_REGISTRY}", 'docker-hub-credentials') {
                        def imageTag = "${DOCKER_REGISTRY}/${DOCKER_IMAGE}:${APP_VERSION}"
                        def latestTag = "${DOCKER_REGISTRY}/${DOCKER_IMAGE}:latest"

                        sh "docker push ${imageTag}"
                        sh "docker push ${latestTag}"
                    }
                }
            }
        }

        stage('Deploy to Production') {
            when {
                anyOf {
                    branch 'master'
                    tag pattern: "v\\d+\\.\\d+\\.\\d+", comparator: "REGEXP"
                }
            }
            steps {
                echo '🚀 Deploying to production...'
                script {
                    // SSH to production server and deploy
                    sshagent(credentials: ['production-server-ssh']) {
                        sh """
                            ssh -o StrictHostKeyChecking=no ${PRODUCTION_USER}@${PRODUCTION_HOST} '
                                cd /opt/pushify && \\
                                git pull origin master && \\
                                export APP_VERSION=${APP_VERSION} && \\
                                docker-compose pull && \\
                                docker-compose up -d --remove-orphans && \\
                                docker-compose exec -T app php bin/console doctrine:migrations:migrate --no-interaction && \\
                                docker-compose exec -T app php bin/console cache:clear --env=prod
                            '
                        """
                    }
                }
            }
        }

        stage('Health Check') {
            when {
                anyOf {
                    branch 'master'
                    tag pattern: "v\\d+\\.\\d+\\.\\d+", comparator: "REGEXP"
                }
            }
            steps {
                echo '🏥 Running health check...'
                script {
                    sleep 10 // Wait for app to start
                    sh """
                        curl -f https://${PRODUCTION_HOST}/health || exit 1
                    """
                }
            }
        }

        stage('Deploy to Beta') {
            when {
                branch 'beta'
            }
            steps {
                echo '🧪 Deploying to beta environment...'
                script {
                    sshagent(credentials: ['beta-server-ssh']) {
                        sh """
                            ssh -o StrictHostKeyChecking=no deploy@beta.pushify.dev '
                                cd /opt/pushify && \\
                                git pull origin beta && \\
                                export APP_VERSION=${APP_VERSION} && \\
                                docker-compose -f docker-compose.beta.yml pull && \\
                                docker-compose -f docker-compose.beta.yml up -d --remove-orphans
                            '
                        """
                    }
                }
            }
        }
    }

    post {
        success {
            echo '✅ Pipeline completed successfully!'
        }

        failure {
            echo '❌ Pipeline failed!'
        }

        always {
            echo '🧹 Cleaning up...'
            script {
                try {
                    // Clean up Docker images (optional)
                    sh 'docker system prune -f || true'
                } catch (Exception e) {
                    echo "Cleanup warning: ${e.message}"
                }
            }
        }
    }
}
