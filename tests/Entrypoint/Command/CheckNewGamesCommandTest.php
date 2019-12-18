<?php declare(strict_types=1);

namespace DemigrantSoft\Tests\Entrypoint\Command;

use DemigrantSoft\Domain\App\AppRepository;
use DemigrantSoft\Domain\App\Model\App;
use DemigrantSoft\Domain\Communication\CommunicationClient;
use DemigrantSoft\Entrypoint\Command\CheckNewGamesCommand;
use DemigrantSoft\Infrastructure\Steam\SteamClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CheckNewGamesCommandTest extends TestCase
{
    private MockObject $client;
    private MockObject $communicationClient;
    private MockObject $appRepository;
    private string $userId;

    private CheckNewGamesCommand $command;

    protected function setUp(): void
    {
        $this->client = $this->createMock(SteamClient::class);
        $this->communicationClient = $this->createMock(CommunicationClient::class);
        $this->appRepository = $this->createMock(AppRepository::class);
        $this->userId = '70000000';

        $this->command = new CheckNewGamesCommand(
            $this->client,
            $this->communicationClient,
            $this->appRepository,
            $this->userId
        );
    }

    /** @test */
    public function given_telegram_disabled_then_do_not_send_group_messages(): void
    {
        $this->communicationClient->expects($this->never())->method('say');

        $this->client->expects($this->once())
            ->method('ownedGames')
            ->with($this->userId)
            ->willReturn([
                'game_count' => 1,
                'games' => [
                    [
                        'appid' => 20,
                        'name' => 'game',
                        'img_icon_url' => 'icon',
                        'img_logo_url' => 'logo',
                    ],
                    [
                        'appid' => 30,
                        'name' => 'game2',
                        'img_icon_url' => 'icon2',
                        'img_logo_url' => 'logo2',
                    ],
                ]
            ]);

        $this->appRepository->expects($this->once())
            ->method('all')
            ->willReturn([20]);

        $app = App::create(30, 'game2', 'icon2', 'logo2');

        $this->appRepository->expects($this->once())
            ->method('save')
            ->with($app);

        $commandTester = new CommandTester($this->command);
        $result = $commandTester->execute(['-t' => 'false']);

        $this->assertEquals(0, $result);
    }

    /** @test */
    public function given_telegram_enabled_then_send_group_messages(): void
    {
        $this->communicationClient->expects($this->exactly(2))->method('say');

        $this->client->expects($this->once())
            ->method('ownedGames')
            ->with($this->userId)
            ->willReturn([
                'game_count' => 1,
                'games' => [
                    [
                        'appid' => 20,
                        'name' => 'game',
                        'img_icon_url' => 'icon',
                        'img_logo_url' => 'logo',
                    ],
                    [
                        'appid' => 30,
                        'name' => 'game2',
                        'img_icon_url' => 'icon2',
                        'img_logo_url' => 'logo2',
                    ],
                ]
            ]);

        $this->appRepository->expects($this->once())
            ->method('all')
            ->willReturn([20]);

        $app = App::create(30, 'game2', 'icon2', 'logo2');

        $this->appRepository->expects($this->once())
            ->method('save')
            ->with($app);

        $commandTester = new CommandTester($this->command);
        $result = $commandTester->execute([]);

        $this->assertEquals(0, $result);
    }

    /** @test */
    public function given_missing_games_then_sync_them(): void
    {
        $this->client->expects($this->once())
            ->method('ownedGames')
            ->with($this->userId)
            ->willReturn([
                'game_count' => 1,
                'games' => [
                    [
                        'appid' => 20,
                        'name' => 'game',
                        'img_icon_url' => 'icon',
                        'img_logo_url' => 'logo',
                    ],
                    [
                        'appid' => 30,
                        'name' => 'game2',
                        'img_icon_url' => 'icon2',
                        'img_logo_url' => 'logo2',
                    ],
                    [
                        'appid' => 40,
                        'name' => 'game3',
                        'img_icon_url' => 'icon3',
                        'img_logo_url' => 'logo3',
                    ],
                ]
            ]);

        $this->appRepository->expects($this->once())
            ->method('all')
            ->willReturn([20]);

        $app = App::create(30, 'game2', 'icon2', 'logo2');
        $app2 = App::create(40, 'game3', 'icon3', 'logo3');

        $this->appRepository->expects($this->exactly(2))
            ->method('save')
            ->withConsecutive([$app], [$app2]);

        $commandTester = new CommandTester($this->command);
        $result = $commandTester->execute(['-t' => 'false']);

        $this->assertEquals(0, $result);
    }

    /** @test */
    public function given_no_missing_games_then_do_nothing(): void
    {
        $this->client->expects($this->once())
            ->method('ownedGames')
            ->with($this->userId)
            ->willReturn([
                'game_count' => 1,
                'games' => [
                    ['appid' => 20],
                    ['appid' => 30],
                    ['appid' => 40],
                ]
            ]);

        $this->appRepository->expects($this->once())
            ->method('all')
            ->willReturn([20, 30, 40]);

        $this->client->expects($this->never())->method('appInfo');
        $this->appRepository->expects($this->never())->method('save');

        $commandTester = new CommandTester($this->command);
        $result = $commandTester->execute(['-t' => 'false']);

        $this->assertEquals(0, $result);
    }

    /** @test */
    public function given_bad_steam_response_then_log(): void
    {
        $this->communicationClient->expects($this->once())->method('log')->with('Fallo en GetOwnedGames');

        $this->client->expects($this->once())
            ->method('ownedGames')
            ->with($this->userId)
            ->willReturn([]);

        $this->appRepository->expects($this->never())->method('all');
        $this->client->expects($this->never())->method('appInfo');
        $this->appRepository->expects($this->never())->method('save');

        $commandTester = new CommandTester($this->command);
        $result = $commandTester->execute(['-t' => 'false']);

        $this->assertEquals(1, $result);
    }
}
