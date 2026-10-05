<?php

namespace Tests\Base\Forge\Controller;

use Base\Entity\User;
use Base\Forge\Controller\Client\BoardController;
use Base\Forge\Entity\Card;
use Base\Forge\Entity\Project;
use Base\Forge\Enum\CardColumn;
use Base\Forge\Enum\ProjectStatus;
use Base\Forge\Service\Board;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\FrameworkBundle\Test\TestBrowserToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The board as a visitor meets it, in a host application (skipped in a bare checkout): its page,
 * a card moved by a request, and who may do either. The requests go straight to the kernel - a
 * host need not have symfony/browser-kit - with the session a browser would carry.
 */
class BoardControllerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    /** @var array<string, string> the cookies of the visitor: their session */
    private array $cookies = [];

    protected function setUp(): void
    {
        $_SERVER['KERNEL_CLASS'] ??= $_ENV['KERNEL_CLASS'] ?? 'App\\Kernel';
        if (!class_exists($_SERVER['KERNEL_CLASS'])) {
            self::markTestSkipped('Needs a host application kernel.');
        }

        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->cookies = [];
    }

    public function testAClientSeesTheirBoardAndMovesACard(): void
    {
        [$client, $project, $cards] = $this->project();
        $this->signIn($client);

        $page = $this->request('GET', '/projets/'.$project->getId().'/tableau');
        self::assertSame(200, $page->getStatusCode(), substr(strip_tags((string) $page->getContent()), 0, 1500));
        $board = $this->xpath($page);
        self::assertSame(1, $board->query('//*[@data-forge-board]')->length);
        self::assertSame(4, $board->query('//*[@data-forge-board-column]')->length, 'the four columns');
        self::assertSame(['Maquette', 'Textes'], $this->titles($board, 'backlog'));
        self::assertSame('/projets/cartes/'.$cards[0]->getId(), $board->evaluate('string(//*[@data-forge-board-card="'.$cards[0]->getId().'"]/@data-url)'));
        $token = $board->evaluate('string(//*[@data-forge-board]/@data-token)');
        self::assertNotSame('', $token, 'a board that moves carries its token');

        // "Textes", then "Maquette" before it, in review.
        $moved = $this->move($cards[1], 'review', 0, $token);
        self::assertSame(200, $moved->getStatusCode());
        self::assertSame(['id' => $cards[1]->getId(), 'column' => 'review', 'position' => 0], json_decode((string) $moved->getContent(), true));
        self::assertSame(200, $this->move($cards[0], 'review', 0, $token)->getStatusCode());

        $this->em->clear();
        $fresh = $this->em->find(Project::class, $project->getId())->getBoard();
        self::assertSame([], $fresh['backlog']);
        self::assertSame(['Maquette', 'Textes'], array_map('strval', $fresh['review']));
        self::assertSame([0, 1], array_map(fn (Card $card) => $card->getPosition(), $fresh['review']));

        // The page says so, each card in its column's colour.
        $board = $this->xpath($this->request('GET', '/projets/'.$project->getId().'/tableau'));
        self::assertSame(['Maquette', 'Textes'], $this->titles($board, 'review'));
        self::assertStringContainsString('is-review', $board->evaluate('string(//*[@data-forge-board-card="'.$cards[0]->getId().'"]/@class)'));
    }

    public function testAMoveIsRefusedWithoutItsTokenOrWithAColumnThatIsNone(): void
    {
        [$client, $project, $cards] = $this->project();
        $this->signIn($client);
        $token = $this->xpath($this->request('GET', '/projets/'.$project->getId().'/tableau'))->evaluate('string(//*[@data-forge-board]/@data-token)');

        self::assertSame(403, $this->move($cards[0], 'done', 0, 'not-the-token')->getStatusCode());
        self::assertSame(422, $this->move($cards[0], 'archive', 0, $token)->getStatusCode());
        self::assertSame(422, $this->move($cards[0], 'done', -1, $token)->getStatusCode());

        $this->em->clear();
        self::assertSame(CardColumn::BACKLOG, $this->em->find(Card::class, $cards[0]->getId())->getColumn(), 'nothing moved');
    }

    public function testSomeoneElseNeitherSeesNorMovesAndADeliveredBoardNoLongerMoves(): void
    {
        [$client, $project, $cards] = $this->project();
        $other = $this->user('other');
        $this->em->flush();

        // The token of the client's own session, for the moves made as them further down.
        $this->signIn($client);
        $token = $this->xpath($this->request('GET', '/projets/'.$project->getId().'/tableau'))->evaluate('string(//*[@data-forge-board]/@data-token)');
        $session = $this->cookies;

        $this->signIn($other);
        self::assertContains($this->request('GET', '/projets/'.$project->getId().'/tableau')->getStatusCode(), [403, 404], 'not their project');
        $theirs = $this->request('GET', '/compte/licences');
        self::assertSame(200, $theirs->getStatusCode(), 'signed in all the same');

        // Delivered: its client still sees it, and nothing moves any more.
        // (Found again: the kernel forgets its entities between two requests.)
        $this->em->find(Project::class, $project->getId())->setStatus(ProjectStatus::DONE);
        $this->em->flush();
        $this->cookies = $session;
        $page = $this->request('GET', '/projets/'.$project->getId().'/tableau');
        self::assertSame(200, $page->getStatusCode());
        $board = $this->xpath($page);
        self::assertSame('', $board->evaluate('string(//*[@data-forge-board]/@data-token)'), 'no token: the script leaves it alone');
        self::assertSame('', $board->evaluate('string(//*[@data-forge-board-card][1]/@data-url)'));
        self::assertContains($this->move($cards[0], 'done', 0, $token)->getStatusCode(), [403, 404]);

        $this->em->clear();
        self::assertSame(CardColumn::BACKLOG, $this->em->find(Card::class, $cards[0]->getId())->getColumn());
    }

    public function testNobodySignedInIsSentToTheLogin(): void
    {
        [, $project] = $this->project();

        $response = $this->request('GET', '/projets/'.$project->getId().'/tableau');
        self::assertContains($response->getStatusCode(), [302, 401, 403]);
    }

    private function move(Card $card, string $column, int $position, string $token): Response
    {
        return $this->request('PATCH', '/projets/cartes/'.$card->getId(), ['HTTP_X_CSRF_TOKEN' => $token, 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode(['column' => $column, 'position' => $position]));
    }

    /** One request to the kernel, with the visitor's cookies; the ones it sets are kept. */
    private function request(string $method, string $uri, array $server = [], ?string $body = null): Response
    {
        $request = Request::create('https://localhost'.$uri, $method, [], $this->cookies, [], $server + ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'PHPUnit'], $body);
        $response = static::$kernel->handle($request);
        foreach ($response->headers->getCookies() as $cookie) {
            $this->cookies[$cookie->getName()] = (string) $cookie->getValue();
        }

        return $response;
    }

    /** As KernelBrowser::loginUser() does: the token in a session, the session's cookie on the visitor. */
    private function signIn(User $user): void
    {
        $context = $this->firewall();
        $session = static::getContainer()->get('session.factory')->createSession();
        $session->set('_security_'.$context, serialize(new TestBrowserToken($user->getRoles(), $user, $context)));
        $session->save();
        $this->cookies = [$session->getName() => $session->getId()];
    }

    private function xpath(Response $response): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());

        return new \DOMXPath($document);
    }

    /** @return list<string> the cards' titles in a column of the page, top to bottom */
    private function titles(\DOMXPath $page, string $column): array
    {
        $titles = [];
        foreach ($page->query('//*[@data-forge-board-column="'.$column.'"]//*[contains(@class, "forge-board-card-title")]') as $node) {
            $titles[] = trim($node->textContent);
        }

        return $titles;
    }

    /** @return array{0: User, 1: Project, 2: list<Card>} a client, their project, its two cards to do */
    private function project(): array
    {
        $client = $this->user('client');
        $this->em->flush();

        $project = new Project($client, 'Site '.bin2hex(random_bytes(3)));
        $project->setClient($client);
        $board = new Board();
        $cards = [$board->add($project, new Card('Maquette')), $board->add($project, new Card('Textes'))];
        $this->em->persist($project);
        $this->em->flush();

        return [$client, $project, $cards];
    }

    private function user(string $name): User
    {
        $class = class_exists('App\\Entity\\User') ? 'App\\Entity\\User' : User::class;
        $name .= '-'.bin2hex(random_bytes(3));
        $user = new $class();
        $user->setUsername($name);
        $user->setEmail($name.'@example.org');
        $user->setPlainPassword($name);
        $user->setRoles(['ROLE_USER']);
        if (method_exists($user, 'verify')) {
            $user->verify();
        }
        $this->em->persist($user);

        return $user;
    }

    /** The context of the firewall the pages are behind ("main", or the one the host shares between its firewalls). */
    private function firewall(): string
    {
        try {
            return static::getContainer()->get('security.firewall.map.config.main')->getContext() ?: 'main';
        } catch (\Throwable) {
            return 'main';
        }
    }
}
