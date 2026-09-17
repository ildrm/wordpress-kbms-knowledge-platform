<?php

declare(strict_types=1);

namespace KBMS\Tests\CoreBackend;

use KBMS\Database\Migrator;
use KBMS\Knowledge\KnowledgeMeta;
use KBMS\Knowledge\KnowledgePostType;
use KBMS\Knowledge\SpaceRepository;
use KBMS\Permissions\AuthorizationService;
use KBMS\Permissions\CapabilityRegistrar;

final class AuthorizationIntegrationTest extends \WP_UnitTestCase
{
    private AuthorizationService $authorization;
    private SpaceRepository $spaces;

    public function set_up(): void
    {
        parent::set_up();
        global $wpdb;
        (new Migrator($wpdb))->migrate();
        (new CapabilityRegistrar())->installCapabilities();
        (new KnowledgePostType())->register();
        (new KnowledgeMeta())->registerMeta();
        $this->authorization = new AuthorizationService($wpdb);
        $this->spaces = new SpaceRepository($wpdb);
    }

    public function testPrivateSpaceIsFailClosedForNonMember(): void
    {
        $owner = self::factory()->user->create(['role' => 'kbms_manager']);
        $outsider = self::factory()->user->create(['role' => 'subscriber']);
        $space = $this->spaces->create('Executive', 'executive', '', 'private', $owner);
        $postId = $this->createIndexedPost($space->id, $owner, 'publish');

        self::assertTrue($this->authorization->can('read', $postId, $owner));
        self::assertFalse($this->authorization->can('read', $postId, $outsider));
        self::assertFalse($this->authorization->can('read', $postId, 0));
        self::assertSame([0], $this->authorization->constrainQueryArgs([], $outsider)['post__in']);
    }

    public function testPublicDraftIsNotReadableAnonymously(): void
    {
        $owner = self::factory()->user->create(['role' => 'kbms_contributor']);
        $space = $this->spaces->create('Docs', 'docs', '', 'public', $owner);
        $postId = $this->createIndexedPost($space->id, $owner, 'draft');

        self::assertFalse($this->authorization->can('read', $postId, 0));
        self::assertTrue($this->authorization->can('read', $postId, $owner));
    }

    private function createIndexedPost(int $spaceId, int $ownerId, string $status): int
    {
        wp_set_current_user($ownerId);
        $postId = self::factory()->post->create([
            'post_type' => KnowledgePostType::POST_TYPE,
            'post_status' => $status,
            'post_author' => $ownerId,
        ]);
        update_post_meta($postId, '_kbms_space_id', $spaceId);
        update_post_meta($postId, '_kbms_owner_id', $ownerId);
        (new KnowledgeMeta())->metaChanged(0, $postId, '_kbms_space_id', $spaceId);
        return $postId;
    }
}
