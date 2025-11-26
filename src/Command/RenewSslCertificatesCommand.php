<?php

namespace App\Command;

use App\Service\DomainService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ssl:renew',
    description: 'Renew SSL certificates that are expiring soon',
)]
class RenewSslCertificatesCommand extends Command
{
    public function __construct(
        private DomainService $domainService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Renewing SSL Certificates');

        $renewed = $this->domainService->renewExpiringCertificates();

        if ($renewed > 0) {
            $io->success("Successfully renewed {$renewed} certificate(s)");
        } else {
            $io->info('No certificates need renewal at this time');
        }

        return Command::SUCCESS;
    }
}
