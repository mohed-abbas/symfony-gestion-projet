<?php

namespace App\Tests\Unit\Service;

use App\Service\AiTaskGenerator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiTaskGeneratorTest extends TestCase
{
    private AiTaskGenerator $generator;

    protected function setUp(): void
    {
        // parseSuggestions est pur (aucun appel réseau) : un stub du client suffit.
        $this->generator = new AiTaskGenerator(
            $this->createStub(HttpClientInterface::class),
            new NullLogger(),
            apiKey: 'test-key',
            model: 'test-model',
            baseUrl: 'https://example.test',
        );
    }

    public function testMalformedJsonReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->generator->parseSuggestions('not json at all'));
    }

    public function testEmptyTasksReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->generator->parseSuggestions('{"tasks": []}'));
    }

    public function testValidPayloadIsNormalized(): void
    {
        $json = json_encode(['tasks' => [
            ['title' => 'Écran de connexion', 'type' => 'story', 'description' => 'Formulaire', 'priority' => 'high'],
        ]]);

        $result = $this->generator->parseSuggestions($json);

        $this->assertCount(1, $result);
        $this->assertSame('Écran de connexion', $result[0]['title']);
        $this->assertSame('story', $result[0]['type']);
        $this->assertSame('high', $result[0]['priority']);
        $this->assertSame('Formulaire', $result[0]['description']);
    }

    public function testUnknownTypeAndPriorityFallBackToDefaults(): void
    {
        $json = json_encode(['tasks' => [
            ['title' => 'Tâche', 'type' => 'epic', 'priority' => 'urgent'],
        ]]);

        $result = $this->generator->parseSuggestions($json);

        $this->assertSame('feature', $result[0]['type']);
        $this->assertSame('medium', $result[0]['priority']);
        $this->assertNull($result[0]['description']);
    }

    public function testEntriesWithoutTitleAreSkipped(): void
    {
        $json = json_encode(['tasks' => [
            ['title' => '', 'type' => 'bug'],
            ['type' => 'bug'],
            ['title' => 'Valide', 'type' => 'bug'],
        ]]);

        $result = $this->generator->parseSuggestions($json);

        $this->assertCount(1, $result);
        $this->assertSame('Valide', $result[0]['title']);
    }

    public function testTitleIsTruncatedTo255Chars(): void
    {
        $json = json_encode(['tasks' => [
            ['title' => str_repeat('a', 300), 'type' => 'feature'],
        ]]);

        $result = $this->generator->parseSuggestions($json);

        $this->assertSame(255, mb_strlen($result[0]['title']));
    }

    public function testBareArrayIsTolerated(): void
    {
        $json = json_encode([
            ['title' => 'Sans clé tasks', 'type' => 'bug', 'priority' => 'low'],
        ]);

        $result = $this->generator->parseSuggestions($json);

        $this->assertCount(1, $result);
        $this->assertSame('bug', $result[0]['type']);
        $this->assertSame('low', $result[0]['priority']);
    }
}
