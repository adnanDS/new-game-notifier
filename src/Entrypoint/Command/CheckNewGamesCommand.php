<?php declare(strict_types=1);

namespace DemigrantSoft\Entrypoint\Command;

use DemigrantSoft\Domain\Communication\CommunicationClient;
use DemigrantSoft\Infrastructure\Steam\SteamClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CheckNewGamesCommand extends Command
{
    private SteamClient $client;
    private CommunicationClient $communicationClient;
    private string $userId;

    public function __construct(
        SteamClient $client,
        CommunicationClient $communicationClient,
        string $userId
    ) {
        $this->client = $client;
        $this->communicationClient = $communicationClient;
        $this->userId = $userId;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Check new games added');
    }

    public function execute(InputInterface $input, OutputInterface $output): void
    {
        $ownedGames = $this->client->ownedGames($this->userId);

        $this->client->appInfo($ownedGames['games'][0]['appid']);
    }
}
