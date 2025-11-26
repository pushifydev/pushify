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
    }

    stages {
        stage('Checkout') {
            steps {
                echo '📦 Checking out code...'
                checkout scm
                sh 'git fetch --tags'
                script {
                    // Read version after checkout
                    env.APP_VERSION = readFile('VERSION').trim()
                    echo "Version: ${env.APP_VERSION}"
                }
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

        stage('Deploy to Production') {
            steps {
                echo '🚀 Deploying to production...'
                script {
                    // SSH to production server and deploy
                    sshagent(credentials: ['production-server-ssh']) {
                        sh """
                            ssh -o StrictHostKeyChecking=no ${PRODUCTION_USER}@${PRODUCTION_HOST} '
                                cd /opt/pushify && \\
                                git pull origin master && \\
                                docker-compose -f docker-compose.prod.yml build && \\
                                docker-compose -f docker-compose.prod.yml up -d --remove-orphans && \\
                                docker-compose -f docker-compose.prod.yml exec -T app php bin/console doctrine:migrations:migrate --no-interaction && \\
                                docker-compose -f docker-compose.prod.yml exec -T app php bin/console cache:clear --env=prod
                            '
                        """
                    }
                }
            }
        }

        stage('Health Check') {
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
            echo '🧹 Pipeline finished'
        }
    }
}
