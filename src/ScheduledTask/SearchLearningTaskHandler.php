<?php declare(strict_types=1);

namespace Nova\Search\ScheduledTask;

use Nova\Search\Rewrite\RewriteService;
use Nova\Search\Service\SearchStatistics;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: SearchLearningTask::class)]
final class SearchLearningTaskHandler extends ScheduledTaskHandler
{
    /**
     * @param EntityRepository<ScheduledTaskCollection> $scheduledTaskRepository
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly RewriteService $rewriteService,
        private readonly SearchStatistics $statistics,
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    public function run(): void
    {
        $this->rewriteService->run();
        $this->statistics->cleanup();
    }
}
