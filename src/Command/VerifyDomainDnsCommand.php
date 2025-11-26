<?php

namespace App\Command;

use App\Repository\DomainRepository;
use App\Service\DomainService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:domain:verify-dns',
    description: 'Verify DNS for all pending domains',
)]
class VerifyDomainDnsCommand extends Command
{
    public function __construct(
        private DomainRepository $domainRepository,
        private DomainService $domainService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Verifying DNS for Pending Domains');

        $pendingDomains = $this->domainRepository->findPendingVerification();

        if (empty($pendingDomains)) {
            $io->info('No pending domains to verify');
            return Command::SUCCESS;
        }

        $verified = 0;
        $failed = 0;

        foreach ($pendingDomains as $domain) {
            $io->text("Checking {$domain->getDomain()}...");

            if ($this->domainService->verifyDns($domain)) {
                $io->text("  ✓ DNS verified");
                $verified++;
            } else {
                $io->text("  ✗ DNS not pointing to server");
                $failed++;
            }
        }

        $io->newLine();
        $io->success("Verified: {$verified}, Failed: {$failed}");

        return Command::SUCCESS;
    }
}
