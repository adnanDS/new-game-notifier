<?php declare(strict_types=1);

namespace DemigrantSoft\Entrypoint\Command;

use DemigrantSoft\Domain\App\AppRepository;
use DemigrantSoft\Domain\App\Model\App;
use DemigrantSoft\Domain\Communication\CommunicationClient;
use DemigrantSoft\Infrastructure\Steam\SteamClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckNewGamesCommand extends Command
{
    private SteamClient $client;
    private CommunicationClient $communicationClient;
    private string $userId;
    private AppRepository $appRepository;

    public function __construct(
        SteamClient $client,
        CommunicationClient $communicationClient,
        AppRepository $appRepository,
        string $userId
    ) {
        $this->client = $client;
        $this->communicationClient = $communicationClient;
        $this->appRepository = $appRepository;
        $this->userId = $userId;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Check new games added')
            ->addOption('notifications', 't', InputOption::VALUE_OPTIONAL, 'Telegram notifications', false);

    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $ownedGames = $this->client->ownedGames($this->userId);

        if (false === isset($ownedGames['games'])) {
            $this->communicationClient->log('Fallo en GetOwnedGames');
            return 0;
        }

        $missingApps = $this->appRepository->missing(
            \array_map(fn($game) => $game['appid'], $ownedGames['games'])
        );

        $toNotify = [];
        \array_walk(
            $missingApps,
            function ($missing) use (&$toNotify) {
                $app = $this->client->appInfo($missing);
                if ($app) {
                    $this->appRepository->save($this->client->appInfo($missing));

                    $toNotify[] = $app;
                }
            }
        );

        if ($this->notificationsEnabled($input) && count($toNotify) > 0) {
            $this->notifyNewGames($toNotify);
        }

        return 0;
    }

    private function notifyNewGames(array $toNotify): void
    {
        $this->communicationClient->say('Nuevos juegos!');

        /** @var App $app */
        foreach ($toNotify as $app) {
            $this->communicationClient->say(
                $app->name() . PHP_EOL
                . 'https://store.steampowered.com/app/' . $app->appid() . PHP_EOL
                . $app->header() . PHP_EOL
            );
        }
    }

    private function notificationsEnabled(InputInterface $input): bool
    {
        if (true === $input->hasParameterOption(['--notifications', '-t'])) {
            return $input->getOption('notifications') === 'true' || $input->getOption('notifications') === null ;
        }

        return true;
    }
}