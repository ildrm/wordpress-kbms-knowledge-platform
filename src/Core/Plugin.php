<?php

declare(strict_types=1);

namespace KBMS\Core;

use KBMS\Admin\Settings;
use KBMS\Admin\KnowledgeMetaBox;
use KBMS\Admin\SpacesPage;
use KBMS\Analytics\Recorder;
use KBMS\AI\AIModule;
use KBMS\AI\GroundedRagPipeline;
use KBMS\AI\LLMProviderInterface;
use KBMS\AI\UnavailableLLMProvider;
use KBMS\Audit\AuditLogger;
use KBMS\Audit\AuditHooks;
use KBMS\BackgroundJobs\Scheduler;
use KBMS\CLI\Commands;
use KBMS\Content\BlockPatterns;
use KBMS\Content\ReusableComponents;
use KBMS\Database\Migrator;
use KBMS\Feedback\Controller as FeedbackController;
use KBMS\Frontend\Portal;
use KBMS\Frontend\Seo;
use KBMS\Graph\Controller as GraphController;
use KBMS\Health\CallbackHealthCheck;
use KBMS\Health\HealthCheckResult;
use KBMS\Health\HealthStatus;
use KBMS\Health\SiteHealth;
use KBMS\Health\SiteHealthIntegration;
use KBMS\Knowledge\KnowledgeMeta;
use KBMS\Knowledge\KnowledgePostType;
use KBMS\Knowledge\SpaceRepository;
use KBMS\Knowledge\VerificationService;
use KBMS\Notifications\ReviewNotifier;
use KBMS\Permissions\AccessController;
use KBMS\Permissions\AuthorizationService;
use KBMS\Permissions\CapabilityRegistrar;
use KBMS\Privacy\Privacy;
use KBMS\Relations\Controller as RelationshipsController;
use KBMS\Relations\RelationshipRepository;
use KBMS\Relations\RelationshipService;
use KBMS\REST\AssistantController;
use KBMS\REST\HealthController;
use KBMS\REST\Routes;
use KBMS\REST\SearchController;
use KBMS\REST\WordPressRoutePermissions;
use KBMS\Search\SearchModule;
use KBMS\Search\WordPressSearchProvider;
use KBMS\Workflow\WorkflowEngine;
use KBMS\Workflow\WorkflowRepository;

/**
 * Runtime composition root. Construction is side-effect free; start() wires hooks.
 */
final class Plugin {

	private bool $started = false;

	/** @var list<Hookable> */
	private array $modules;

	public function __construct( ?array $modules = null ) {
		if ( $modules !== null ) {
			$this->modules = $modules;
			return;
		}

		global $wpdb;

		$audit              = new AuditLogger( $wpdb );
		$authorization      = new AuthorizationService( $wpdb );
		$workflowRepository = new WorkflowRepository( $wpdb );
		$spaceRepository    = new SpaceRepository( $wpdb );
		$search             = new WordPressSearchProvider( $authorization );
		$llm                = apply_filters( 'kbms_llm_provider', new UnavailableLLMProvider() );
		if ( ! $llm instanceof LLMProviderInterface ) {
			$llm = new UnavailableLLMProvider();
		}
		$confidence       = max( 0.0, min( 1.0, ( (int) Settings::get( 'confidence_threshold' ) ) / 100 ) );
		$rag              = new GroundedRagPipeline( $search, $llm, $authorization, null, $confidence );
		$routePermissions = new WordPressRoutePermissions();
		$relationships    = new RelationshipService( new RelationshipRepository( $wpdb ), $authorization );
		$health           = new HealthStatus(
			array(
				new CallbackHealthCheck(
					'database',
					static function (): HealthCheckResult {
						$current = (int) get_option( Migrator::OPTION, 0 );
						return $current >= 1
						? new HealthCheckResult( HealthCheckResult::GOOD, __( 'Database schema is current', 'wp-kbms' ), __( 'All registered migrations have completed.', 'wp-kbms' ) )
						: new HealthCheckResult( HealthCheckResult::CRITICAL, __( 'Database migration is incomplete', 'wp-kbms' ), __( 'Run plugin activation or inspect the audit log.', 'wp-kbms' ) );
					}
				),
				new CallbackHealthCheck(
					'ai',
					static function () use ( $llm ): HealthCheckResult {
						if ( ! Settings::get( 'ai_enabled' ) ) {
							return new HealthCheckResult( HealthCheckResult::GOOD, __( 'AI is disabled', 'wp-kbms' ), __( 'Lexical search and all core knowledge workflows remain available.', 'wp-kbms' ) );
						}
						return $llm instanceof UnavailableLLMProvider
						? new HealthCheckResult( HealthCheckResult::RECOMMENDED, __( 'AI provider is not configured', 'wp-kbms' ), __( 'The assistant will return a controlled insufficient-evidence response.', 'wp-kbms' ) )
						: new HealthCheckResult( HealthCheckResult::GOOD, __( 'AI provider is configured', 'wp-kbms' ), __( 'The provider adapter is available.', 'wp-kbms' ) );
					}
				),
			)
		);

		$controllers = array( new SearchController( $search, $routePermissions ), new HealthController( $health, $routePermissions ) );
		if ( Settings::get( 'ai_enabled' ) ) {
			$controllers[] = new AssistantController( $rag, $routePermissions );
		}

		$this->modules = array(
			new Migrator( $wpdb, $audit ),
			new AuditHooks( $audit ),
			new CapabilityRegistrar(),
			new KnowledgePostType(),
			new KnowledgeMeta(),
			new AccessController( $authorization ),
			new VerificationService( $wpdb, $authorization, $audit ),
			new WorkflowEngine( $workflowRepository, $authorization, $audit ),
			new Settings(),
			new SpacesPage( $spaceRepository ),
			new KnowledgeMetaBox( $spaceRepository ),
			new BlockPatterns(),
			new ReusableComponents(),
			new SearchModule( $search ),
			new AIModule( $rag ),
			new Routes( $controllers ),
			new RelationshipsController( $relationships ),
			new GraphController( $relationships ),
			new FeedbackController( $wpdb, $authorization ),
			new Portal( $authorization ),
			new Seo(),
			new Scheduler(),
			new ReviewNotifier(),
			new Privacy( $wpdb ),
			new Recorder( $wpdb, $authorization ),
			new SiteHealth(),
			new SiteHealthIntegration( $health ),
			new Commands(),
		);
	}

	public function start(): void {
		if ( $this->started ) {
			return;
		}

		foreach ( $this->modules as $module ) {
			$module->registerHooks();
		}

		$this->started = true;
	}
}
